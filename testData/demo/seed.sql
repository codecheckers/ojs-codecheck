-- CODECHECK plugin — live demo seed.
--
-- Applied by `make demo-db` on top of the test dataset
-- (testData/stable-3_5_0-codecheck), never on its own and never by the test
-- suites: the e2e specs depend on the dataset as it ships, and this file
-- changes exactly what they assert on (status history, submission 2's
-- repositories, which submissions exist). `make db-reset` puts the test
-- dataset back.
--
-- Walkthrough and set-up: dev/live-demo.md.
--
-- What it adds, by submission, is tabled in dev/live-demo.md, "What the demo
-- dataset contains"; each section below says why.
--
-- Keep it in step with the schema: a change to codecheck_metadata, or to the
-- JSON inside it, has to be made here as well as in the test dataset.

SET NAMES utf8mb4;

-- All or nothing: the client stops at the first error and the open
-- transaction is rolled back when it disconnects.
START TRANSACTION;

-- ---------------------------------------------------------------------------
-- The journal
-- ---------------------------------------------------------------------------
--
-- The test dataset's journal is disabled, and OJS sends a logged-out visitor to
-- a disabled journal's login page — so readers would see nothing.
UPDATE journals SET enabled = 1 WHERE journal_id = 1;

-- ---------------------------------------------------------------------------
-- Plugin settings (journal 1)
-- ---------------------------------------------------------------------------
--
-- The register is the testing one.
--
-- The register deposit is switched off, so publishing during the demo does not
-- open a pull request against the testing register unannounced. Turn it on in
-- the settings form to show it.

INSERT INTO plugin_settings (plugin_name, context_id, setting_name, setting_value, setting_type) VALUES
  ('codecheckplugin', 1, 'githubRegisterOrganization', 'codecheckers', 'string'),
  ('codecheckplugin', 1, 'githubRegisterRepository', 'testing-dev-register', 'string'),
  ('codecheckplugin', 1, 'codecheckGithubUpdateFields', '["updateTitle","updateBody","updateStatus"]', 'object'),
  ('codecheckplugin', 1, 'codecheckStatusKeysSelected', '["plugins.generic.codecheck.status.completed.partialReproduction","plugins.generic.codecheck.status.completed.fullReproduction","plugins.generic.codecheck.status.publishedCertificate.partialReproduction","plugins.generic.codecheck.status.publishedCertificate.fullReproduction"]', 'object'),
  ('codecheckplugin', 1, 'codecheckRegisterDepositEnabled', '0', 'bool'),
  ('codecheckplugin', 1, 'showArticleSidebar', '1', 'bool'),
  ('codecheckplugin', 1, 'showInTOC', '1', 'bool'),
  ('codecheckplugin', 1, 'showDashboardColumn', '1', 'bool'),
  ('codecheckplugin', 1, 'showAvailabilityStatement', '1', 'bool'),
  -- Empty: the token is a secret. make demo-db writes it into this row, which
  -- the test dataset does not have.
  ('codecheckplugin', 1, 'githubPersonalAccessToken', '', 'string')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_type = VALUES(setting_type);

-- ---------------------------------------------------------------------------
-- Submission 2: the author's code, an archive with the data, the codechecker's copy
-- ---------------------------------------------------------------------------
--
-- Zenodo 10.5281/zenodo.4139727 is the archived release of the author's
-- repository, which carries the natural images the figures are computed from.

UPDATE codecheck_metadata
   SET repository = '{"repositories":[{"url":"https://github.com/IainDaviesMaths/Reproduction-Hancock","hidden":false,"providedByAuthor":true,"containsCodecheckYaml":false},{"url":"https://doi.org/10.5281/zenodo.4139727","hidden":false,"providedByAuthor":true,"containsCodecheckYaml":false},{"url":"https://github.com/codecheckers/Reproduction-Hancock","hidden":false,"providedByAuthor":false,"containsCodecheckYaml":true}]}'
 WHERE submission_id = 2;

UPDATE publication_settings
   SET setting_value = 'The MATLAB and Julia code is available at https://github.com/IainDaviesMaths/Reproduction-Hancock. The code and the natural images used as input are archived at https://doi.org/10.5281/zenodo.4139727.'
 WHERE publication_id = 2 AND setting_name = 'dataAvailabilityStatement';

-- The codechecker's repositories of 3, 4 and 8 do hold a codecheck.yml (checked
-- 2026-09-30). Marking them shows the flag on the article page, and gives the
-- private-repository refusal on 8 something to refuse.
-- Each has one repository, and the dump writes JSON with and without spaces.
UPDATE codecheck_metadata
   SET repository = REPLACE(REPLACE(repository,
         '"containsCodecheckYaml": false', '"containsCodecheckYaml": true'),
         '"containsCodecheckYaml":false', '"containsCodecheckYaml":true')
 WHERE submission_id IN (3, 4, 8);

-- ---------------------------------------------------------------------------
-- Status histories
-- ---------------------------------------------------------------------------
--
-- user_id -1 is the automatic update, 5 is jmanager (journal manager).
-- The table is append-only and the newest row is the current status.

INSERT INTO codecheck_status (submission_id, status, timestamp, user_id) VALUES
  -- 2: complete, certificate published
  (2, 'plugins.generic.codecheck.status.needsCodechecker',                            '2020-03-02 09:00:00', -1),
  (2, 'plugins.generic.codecheck.status.assignedCodechecker',                         '2020-03-09 14:20:00',  5),
  (2, 'plugins.generic.codecheck.status.completed.fullReproduction',                  '2020-04-13 11:00:00',  5),
  (2, 'plugins.generic.codecheck.status.publishedCertificate.fullReproduction',       '2020-04-15 10:00:00',  5),
  -- 3
  (3, 'plugins.generic.codecheck.status.needsCodechecker',                            '2019-01-21 09:00:00', -1),
  (3, 'plugins.generic.codecheck.status.assignedCodechecker',                         '2019-01-28 10:00:00',  5),
  (3, 'plugins.generic.codecheck.status.completed.fullReproduction',                  '2019-02-14 10:00:00',  5),
  (3, 'plugins.generic.codecheck.status.publishedCertificate.fullReproduction',       '2019-02-20 10:00:00',  5),
  -- 4: went back to the author once
  (4, 'plugins.generic.codecheck.status.needsCodechecker',                            '2020-05-11 09:00:00', -1),
  (4, 'plugins.generic.codecheck.status.assignedCodechecker',                         '2020-05-14 16:00:00',  5),
  (4, 'plugins.generic.codecheck.status.stalled.author',                              '2020-05-22 12:00:00',  5),
  (4, 'plugins.generic.codecheck.status.assignedCodechecker',                         '2020-05-29 09:30:00',  5),
  (4, 'plugins.generic.codecheck.status.completed.fullReproduction',                  '2020-06-02 13:00:00',  5),
  (4, 'plugins.generic.codecheck.status.publishedCertificate.fullReproduction',       '2020-06-05 10:00:00',  5),
  -- 5
  (5, 'plugins.generic.codecheck.status.needsCodechecker',                            '2020-06-22 09:00:00', -1),
  (5, 'plugins.generic.codecheck.status.assignedCodechecker',                         '2020-06-29 11:00:00',  5),
  (5, 'plugins.generic.codecheck.status.completed.fullReproduction',                  '2020-07-13 12:32:00',  5),
  (5, 'plugins.generic.codecheck.status.publishedCertificate.fullReproduction',       '2020-07-16 10:00:00',  5),
  -- 7: codechecker assigned at once
  (7, 'plugins.generic.codecheck.status.assignedCodechecker',                         '2022-06-20 09:00:00', -1),
  (7, 'plugins.generic.codecheck.status.completed.fullReproduction',                  '2022-07-09 13:00:00',  5),
  (7, 'plugins.generic.codecheck.status.publishedCertificate.fullReproduction',       '2022-07-12 10:00:00',  5),
  -- 8: completed, certificate not yet published
  (8, 'plugins.generic.codecheck.status.needsCodechecker',                            '2022-09-05 09:00:00', -1),
  (8, 'plugins.generic.codecheck.status.assignedCodechecker',                         '2022-09-12 10:00:00',  5),
  (8, 'plugins.generic.codecheck.status.completed.fullReproduction',                  '2022-09-27 01:00:00',  5),
  -- 9: in progress
  (9, 'plugins.generic.codecheck.status.needsCodechecker',                            '2026-09-01 09:00:00', -1),
  (9, 'plugins.generic.codecheck.status.assignedCodechecker',                         '2026-09-08 15:00:00',  5),
  -- 10: stalled on the codechecker's side
  (10, 'plugins.generic.codecheck.status.needsCodechecker',                           '2026-08-03 09:00:00', -1),
  (10, 'plugins.generic.codecheck.status.assignedCodechecker',                        '2026-08-10 09:00:00',  5),
  (10, 'plugins.generic.codecheck.status.stalled.codechecker',                        '2026-09-14 09:00:00',  5);

-- Submission 9 goes into issue 2 and on to production, so OJS has nothing to
-- object to and publishing it is refused by CODECHECK alone: the check is
-- still running. In review, OJS refuses it too, for the stage.
UPDATE publications SET issue_id = 2 WHERE publication_id = 9;
UPDATE submissions SET stage_id = 5 WHERE submission_id = 9;

-- ---------------------------------------------------------------------------
-- New submissions 11–13
-- ---------------------------------------------------------------------------
--
-- Shaped like the dataset's own: one author stage assignment (user group 14),
-- section 1, English, and the dataset's sample PDF (file 8) as the manuscript.
-- Titles, authors and abstracts are those of real, openly published papers;
-- the submitting accounts are the dataset's author users.

INSERT INTO submissions (submission_id, context_id, current_publication_id, date_last_activity, date_submitted, last_modified, stage_id, locale, status, submission_progress, work_type) VALUES
  (11, 1, NULL, '2026-09-29 16:40:00', NULL,                  '2026-09-29 16:40:00', 1, 'en', 1, 'details', 0),
  (12, 1, NULL, '2026-09-22 10:05:00', '2026-09-22 10:05:00', '2026-09-22 10:05:00', 1, 'en', 1, '',        0),
  (13, 1, NULL, '2026-09-24 14:30:00', '2026-09-24 14:30:00', '2026-09-24 14:30:00', 1, 'en', 1, '',        0);

INSERT INTO publications (publication_id, access_status, date_published, last_modified, primary_contact_id, section_id, seq, submission_id, status, url_path, version, doi_id, issue_id) VALUES
  (11, 0, NULL, '2026-09-29 16:40:00', NULL, 1, 0, 11, 1, NULL, 1, NULL, NULL),
  (12, 0, NULL, '2026-09-22 10:05:00', NULL, 1, 0, 12, 1, NULL, 1, NULL, NULL),
  (13, 0, NULL, '2026-09-24 14:30:00', NULL, 1, 0, 13, 1, NULL, 1, NULL, NULL);

INSERT INTO publication_settings (publication_id, locale, setting_name, setting_value) VALUES
  (11, 'en', 'title', 'Improving reproducibility of GIScience publications through novel reproducibility guidelines and revised review procedures'),
  (11, 'en', 'abstract', '<p>Recent research in the field of geographic information science shows that reproducibility and replicability of publications have substantial room for improvement and proposes various actions to improve the situation. However, the impact of these actions remains unclear. This study investigates the combined effect of novel author guidelines and workflow review process, which award badges for successful reproductions, on the potential reproducibility of articles published in the AGILE conference series proceedings over the past decade. While replicating the approach of previous studies, this work expands the scope of prior reproducibility assessments and systematically compares the findings for the AGILE conference with those of the GIScience conference series proceedings, which has not undergone similar changes to guidelines or procedures. Results indicate that the reproducibility guidelines and the review process measurably improved the potential reproducibility of AGILE publications. The comparison with GIScience papers further suggests that clear and enforced guidance is a key driver for change. Our findings demonstrate the value of institutional policies and community norms in fostering reproducible research in the GIScience field and identify pathways for its ongoing improvement.</p>'),
  (12, 'en', 'title', 'ClockBoard: A zoning system for urban analysis'),
  (12, 'en', 'abstract', '<p>Zones are the building blocks of urban analysis. Fields ranging from demographics to transport planning routinely use zones - spatially contiguous areal units that break-up continuous space into discrete chunks - as the foundation for diverse analysis techniques. Key methods such as origin-destination analysis and choropleth mapping rely on zones with appropriate sizes, shapes and coverage. However, existing zoning systems are sub-optimal in many urban analysis contexts, for three main reasons: 1) administrative zoning systems are often based on somewhat arbitrary factors; 2) zoning systems that are evidence-based (e.g., based on equal population size) are often highly variable in size and shape, reducing their utility for inter-city comparison; and 3) official zoning systems in many places simply do not exist or are unavailable. We set out to develop a flexible, open and scalable solution to these problems. The result is the zonebuilder project (with R, Rust and Python implementations), which was used to create the ClockBoard zoning system. ClockBoard consists of 12 segments emanating from a central place and divided by concentric rings with radii that increase in line with the triangular number sequence (1, 3, 6 km etc). ''ClockBoards'' thus create a consistent visual frame of reference for monocentric cities that is reminiscent of clocks and a dartboard. This paper outlines the design and potential uses of the ClockBoard zoning system in the historical context, and discusses future avenues for research into the design and assessment of zoning systems.</p>'),
  (12, '',   'dataAvailabilityStatement', 'The zonebuilder R package used to create all figures is available at https://github.com/zonebuilders/zonebuilder and on CRAN.'),
  (13, 'en', 'title', 'Exploring Categorical Colors'),
  (13, 'en', 'abstract', '<p>Language often uses categorical values (e.g., red, pink) when science provides continuous values (e.g,. rgb[255,0,0], rgb[255,0,125]). I presented participants with colors across a red (rgb[255,0,0]), purple (rgb[255,0,255]), and blue (rgb[0,0,255]) spectrum and explored whether remembered colors were assimilated towards prototypical colors (red, pink, purple, blue). This was the case for different shades of pink but it is uncertain whether this was due to the category hypothesis being correct, computer screens being unable to present colors correctly, or other reasons. The study''s code and data are available online (https://osf.io/86zv9/).</p>');

INSERT INTO authors (author_id, email, include_in_browse, publication_id, seq, user_group_id) VALUES
  (31, 'cgranell@mailinator.com',   1, 11, 0, 14),
  (32, 'fostermann@mailinator.com', 1, 11, 1, 14),
  (33, 'dnuest@mailinator.com',     1, 11, 2, 14),
  (34, 'pkedron@mailinator.com',    1, 11, 3, 14),
  (35, 'ekoukouraki@mailinator.com',1, 11, 4, 14),
  (36, 'mmatey@mailinator.com',     1, 11, 5, 14),
  (37, 'rdecoupes@mailinator.com',  1, 11, 6, 14),
  (38, 'strilles@mailinator.com',   1, 11, 7, 14),
  (39, 'agraser@mailinator.com',    1, 11, 8, 14),
  (40, 'tniers@mailinator.com',     1, 11, 9, 14),
  (41, 'rlovelace@mailinator.com',  1, 12, 0, 14),
  (42, 'mtennekes@mailinator.com',  1, 12, 1, 14),
  (43, 'dcarlino@mailinator.com',   1, 12, 2, 14),
  (44, 'lroeseler@mailinator.com',  1, 13, 0, 14);

INSERT INTO author_settings (author_id, locale, setting_name, setting_value) VALUES
  (31, 'en', 'givenName', 'Carlos'),    (31, 'en', 'familyName', 'Granell'),     (31, '', 'country', 'ES'),
  (32, 'en', 'givenName', 'Frank O.'),  (32, 'en', 'familyName', 'Ostermann'),   (32, '', 'country', 'NL'),
  (33, 'en', 'givenName', 'Daniel'),    (33, 'en', 'familyName', 'Nüst'),        (33, '', 'country', 'DE'),
  (34, 'en', 'givenName', 'Peter'),     (34, 'en', 'familyName', 'Kedron'),      (34, '', 'country', 'US'),
  (35, 'en', 'givenName', 'Eftychia'),  (35, 'en', 'familyName', 'Koukouraki'),  (35, '', 'country', 'DE'),
  (36, 'en', 'givenName', 'Miguel'),    (36, 'en', 'familyName', 'Matey-Sanz'),  (36, '', 'country', 'ES'),
  (37, 'en', 'givenName', 'Rémy'),      (37, 'en', 'familyName', 'Decoupes'),    (37, '', 'country', 'FR'),
  (38, 'en', 'givenName', 'Sergio'),    (38, 'en', 'familyName', 'Trilles'),     (38, '', 'country', 'ES'),
  (39, 'en', 'givenName', 'Anita'),     (39, 'en', 'familyName', 'Graser'),      (39, '', 'country', 'AT'),
  (40, 'en', 'givenName', 'Tom'),       (40, 'en', 'familyName', 'Niers'),       (40, '', 'country', 'DE'),
  (41, 'en', 'givenName', 'Robin'),     (41, 'en', 'familyName', 'Lovelace'),    (41, '', 'country', 'GB'),
  (42, 'en', 'givenName', 'Martijn'),   (42, 'en', 'familyName', 'Tennekes'),    (42, '', 'country', 'NL'),
  (43, 'en', 'givenName', 'Dustin'),    (43, 'en', 'familyName', 'Carlino'),     (43, '', 'country', 'US'),
  (44, 'en', 'givenName', 'Lukas'),     (44, 'en', 'familyName', 'Röseler'),     (44, '', 'country', 'DE');

-- The three rows point at each other, so the links are made once all exist.
UPDATE submissions SET current_publication_id = submission_id WHERE submission_id IN (11, 12, 13);
UPDATE publications SET primary_contact_id = 32 WHERE publication_id = 11;
UPDATE publications SET primary_contact_id = 41 WHERE publication_id = 12;
UPDATE publications SET primary_contact_id = 44 WHERE publication_id = 13;

-- Submitters: fostermann (4), seglen (3), dnuest (2). can_change_metadata is 1,
-- as OJS sets it for whoever starts a submission: without it the author's own
-- draft refuses every save with a 401, and the wizard loses what they entered.
INSERT INTO stage_assignments (submission_id, user_group_id, user_id, date_assigned, recommend_only, can_change_metadata) VALUES
  (11, 14, 4, '2026-09-29 16:30:00', 0, 1),
  (12, 14, 3, '2026-09-22 10:00:00', 0, 1),
  (13, 14, 2, '2026-09-24 14:25:00', 0, 1);

-- The manuscript: the dataset's sample PDF (file 8), shared rather than copied,
-- so nothing has to be put into files_dir.
INSERT INTO submission_files (submission_file_id, submission_id, file_id, source_submission_file_id, genre_id, file_stage, direct_sales_price, sales_type, viewable, created_at, updated_at, uploader_user_id, assoc_type, assoc_id) VALUES
  (17, 11, 8, NULL, 1, 2, NULL, NULL, NULL, '2026-09-29 16:35:00', '2026-09-29 16:35:00', 4, NULL, NULL),
  (18, 12, 8, NULL, 1, 2, NULL, NULL, NULL, '2026-09-22 10:02:00', '2026-09-22 10:02:00', 3, NULL, NULL),
  (19, 13, 8, NULL, 1, 2, NULL, NULL, NULL, '2026-09-24 14:27:00', '2026-09-24 14:27:00', 2, NULL, NULL);

INSERT INTO submission_file_settings (submission_file_id, locale, setting_name, setting_value) VALUES
  (17, 'en', 'name', 'granell-et-al-manuscript.pdf'),
  (18, 'en', 'name', 'clockboard-manuscript.pdf'),
  (19, 'en', 'name', 'categorical-colors-manuscript.pdf');

-- 11 and 12 opted in on the start form; 13 did not, which leaves no row at
-- all, as the start form does.
INSERT INTO submission_settings (submission_id, locale, setting_name, setting_value) VALUES
  (11, '', 'codecheckOptIn', '1'),
  (12, '', 'codecheckOptIn', '1');

-- 11 has no CODECHECK record yet: the wizard writes it when the author saves.
-- 12 carries what its author entered in the wizard.
INSERT INTO codecheck_metadata (submission_id, spec_version, publication_type, manifest, repository, source, codecheckers, certificate, issue, check_time, summary, report, additional_content, created_at, updated_at) VALUES
  (12, '1.0', 'doi',
   '[{"file":"figure2_clockboard.png","comment":"Figure 2: the ClockBoard zoning system for London","hidden":false,"providedByAuthor":true},{"file":"figure3_doughnuts_segments.png","comment":"Figure 3: doughnut and segment zones","hidden":false,"providedByAuthor":true},{"file":"figure4_grid.png","comment":"Figure 4: comparison with a rectangular grid","hidden":false,"providedByAuthor":true}]',
   '{"repositories":[{"url":"https://github.com/zonebuilders/zonebuilder","hidden":false,"providedByAuthor":true,"containsCodecheckYaml":false}]}',
   NULL, '[]', NULL, '{"url":null,"number":null,"labelsSelected":[]}', NULL, NULL, NULL, NULL,
   '2026-09-22 10:05:00', '2026-09-22 10:05:00');

INSERT INTO codecheck_status (submission_id, status, timestamp, user_id) VALUES
  (12, 'plugins.generic.codecheck.status.needsCodechecker', '2026-09-22 10:05:00', -1);

-- Recorded when they happened, as the status handler would have.
-- `timestamp` is ON UPDATE CURRENT_TIMESTAMP: assigning it keeps it.
UPDATE codecheck_status SET timestamp = timestamp, created_at = timestamp, updated_at = timestamp
 WHERE created_at IS NULL;

COMMIT;
