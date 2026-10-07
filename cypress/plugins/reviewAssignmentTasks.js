/**
 * Putting a review assignment back as it was, for the one spec that closes a
 * review (#13). OJS cannot reopen a submitted review, through its interface or
 * its API, so without this `close-review.cy.js` could close the review only
 * once per dataset load, against the rule that specs restore what they change.
 *
 * Runs in Cypress's Node process and reaches the database with the `mysql`
 * client, configured by `CYPRESS_DB_HOST`, `_PORT`, `_NAME`, `_USER` and
 * `_PASS` — which `make test-e2e` and CI set. Deliberately narrow: a snapshot of
 * one assignment and its round, and putting exactly that back, with the review
 * comment and the notifications added since, in one transaction. "Since" is
 * the highest id at the snapshot, which assumes the suite runs one spec at a
 * time. The event log keeps its entries, as the status history keeps its rows.
 */

import { spawnSync } from 'node:child_process';

/** `ASSOC_TYPE_REVIEW_ASSIGNMENT` and `SubmissionComment::COMMENT_TYPE_PEER_REVIEW` in OJS. */
const ASSOC_TYPE_REVIEW_ASSIGNMENT = 517;
const COMMENT_TYPE_PEER_REVIEW = 1;

function query(env, sql) {
  for (const name of ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS']) {
    if (env[name] === undefined || env[name] === '') {
      throw new Error(`CYPRESS_${name} is not set: the review-assignment tasks need the database (see cypress/plugins/reviewAssignmentTasks.js).`);
    }
  }
  // The password in the environment, not on the command line, where `ps` shows it.
  const result = spawnSync('mysql', [
    '-h', String(env.DB_HOST), '-P', String(env.DB_PORT), '-u', String(env.DB_USER),
    '-N', '-B', String(env.DB_NAME), '-e', sql,
  ], { encoding: 'utf8', env: { ...process.env, MYSQL_PWD: String(env.DB_PASS) } });
  if (result.status !== 0) {
    throw new Error(`mysql failed: ${result.stderr || result.error}`);
  }
  return result.stdout.trim();
}

/** A value read back from the snapshot, as SQL: only NULL, numbers and dates occur. */
function literal(value) {
  if (value === null || value === undefined) {
    return 'NULL';
  }
  if (typeof value === 'number') {
    return String(value);
  }
  if (!/^[\d :.-]+$/.test(String(value))) {
    throw new Error(`Unexpected value in a review-assignment snapshot: ${value}`);
  }
  return `'${value}'`;
}

export function reviewAssignmentTasks(env) {
  const id = (reviewId) => {
    const number = Number(reviewId);
    if (!Number.isInteger(number) || number <= 0) {
      throw new Error(`Not a review assignment id: ${reviewId}`);
    }
    return number;
  };

  return {
    /** The assignment's state, its round's status and the newest comment and notification ids. */
    snapshotReviewAssignment(reviewId) {
      const review = id(reviewId);
      return JSON.parse(query(env, `
        SELECT JSON_OBJECT(
          'reviewId', ra.review_id,
          'dateConfirmed', ra.date_confirmed,
          'dateCompleted', ra.date_completed,
          'recommendation', ra.recommendation,
          'step', ra.step,
          'reviewRoundId', ra.review_round_id,
          'roundStatus', rr.status,
          'lastCommentId', (SELECT COALESCE(MAX(comment_id), 0) FROM submission_comments),
          'lastNotificationId', (SELECT COALESCE(MAX(notification_id), 0) FROM notifications))
        FROM review_assignments ra JOIN review_rounds rr USING (review_round_id)
        WHERE ra.review_id = ${review}`));
    },

    /** Puts the assignment back as snapshotted, removing the comment and notifications added since. */
    restoreReviewAssignment(snapshot) {
      const review = id(snapshot.reviewId);
      query(env, `
        START TRANSACTION;
        UPDATE review_assignments SET
          date_confirmed = ${literal(snapshot.dateConfirmed)},
          date_completed = ${literal(snapshot.dateCompleted)},
          recommendation = ${literal(snapshot.recommendation)},
          step = ${literal(snapshot.step)}
        WHERE review_id = ${review};
        UPDATE review_rounds SET status = ${literal(snapshot.roundStatus)} WHERE review_round_id = ${id(snapshot.reviewRoundId)};
        DELETE FROM submission_comments
         WHERE comment_type = ${COMMENT_TYPE_PEER_REVIEW} AND assoc_id = ${review}
           AND comment_id > ${literal(snapshot.lastCommentId)};
        DELETE FROM notifications
         WHERE assoc_type = ${ASSOC_TYPE_REVIEW_ASSIGNMENT} AND assoc_id = ${review}
           AND notification_id > ${literal(snapshot.lastNotificationId)};
        COMMIT;`);
      return null;
    },
  };
}
