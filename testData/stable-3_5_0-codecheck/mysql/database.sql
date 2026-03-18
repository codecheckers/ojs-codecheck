-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Mar 18, 2026 at 02:00 AM
-- Server version: 8.0.40
-- PHP Version: 8.2.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ojs`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` bigint NOT NULL,
  `assoc_type` smallint DEFAULT NULL,
  `assoc_id` bigint DEFAULT NULL,
  `type_id` bigint DEFAULT NULL,
  `date_expire` date DEFAULT NULL,
  `date_posted` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Announcements are messages that can be presented to users e.g. on the homepage.';

-- --------------------------------------------------------

--
-- Table structure for table `announcement_settings`
--

CREATE TABLE `announcement_settings` (
  `announcement_setting_id` bigint UNSIGNED NOT NULL,
  `announcement_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about announcements, including localized properties like names and contents.';

-- --------------------------------------------------------

--
-- Table structure for table `announcement_types`
--

CREATE TABLE `announcement_types` (
  `type_id` bigint NOT NULL,
  `context_id` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Announcement types allow for announcements to optionally be categorized.';

-- --------------------------------------------------------

--
-- Table structure for table `announcement_type_settings`
--

CREATE TABLE `announcement_type_settings` (
  `announcement_type_setting_id` bigint UNSIGNED NOT NULL,
  `type_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about announcement types, including localized properties like their names.';

-- --------------------------------------------------------

--
-- Table structure for table `authors`
--

CREATE TABLE `authors` (
  `author_id` bigint NOT NULL,
  `email` varchar(90) NOT NULL,
  `include_in_browse` smallint NOT NULL DEFAULT '1',
  `publication_id` bigint NOT NULL,
  `seq` double NOT NULL DEFAULT '0',
  `user_group_id` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='The authors of a publication.';

--
-- Dumping data for table `authors`
--

INSERT INTO `authors` (`author_id`, `email`, `include_in_browse`, `publication_id`, `seq`, `user_group_id`) VALUES
(4, 'phancock@mailinator.com', 1, 2, 0, 14),
(5, 'rbaddeley@mailinator.com', 1, 2, 0, 14),
(6, 'lsmith@mailinator.com', 1, 2, 0, 14),
(7, 'tlee@mailinator.com', 1, 3, 0, 14),
(8, 'esuh@mailinator.com', 1, 3, 0, 14),
(9, 'khill@mailinator.com', 1, 3, 0, 14),
(10, 'spiccolo@mailinator.com', 1, 3, 0, 14),
(11, 'cbrunsdon@mailinator.com', 1, 4, 0, 14),
(12, 'acomber@mailinator.com', 1, 4, 0, 14),
(13, 'dnuest@mailinator.com', 1, 5, 0, 14),
(15, 'enuhn@mailinator.com', 1, 7, 0, 14),
(16, 'fkönig@mailinator.com', 1, 7, 0, 14),
(17, 'stimpf@mailinator.com', 1, 7, 0, 14),
(18, 'rdong@mailinator.com', 1, 8, 0, 14),
(19, 'dcameron@mailinator.com', 1, 8, 0, 14),
(20, 'jbedo@mailinator.com', 1, 8, 0, 14),
(21, 'apapenfuss@mailinator.com', 1, 8, 0, 14),
(22, 'zliu@mailinator.com', 1, 9, 0, 14),
(23, 'kjanowicz@mailinator.com', 1, 9, 0, 14),
(24, 'lcai@mailinator.com', 1, 9, 0, 14),
(25, 'rzhu@mailinator.com', 1, 9, 0, 14),
(26, 'gmai@mailinator.com', 1, 9, 0, 14),
(27, 'mshi@mailinator.com', 1, 9, 0, 14),
(28, 'enuhn@mailinator.com', 1, 10, 0, 14),
(29, 'khamburger@mailinator.com', 1, 10, 0, 14),
(30, 'stimpf@mailinator.com', 1, 10, 0, 14);

-- --------------------------------------------------------

--
-- Table structure for table `author_affiliations`
--

CREATE TABLE `author_affiliations` (
  `author_affiliation_id` bigint NOT NULL,
  `author_id` bigint NOT NULL,
  `ror` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Author affiliations';

-- --------------------------------------------------------

--
-- Table structure for table `author_affiliation_settings`
--

CREATE TABLE `author_affiliation_settings` (
  `author_affiliation_setting_id` bigint UNSIGNED NOT NULL,
  `author_affiliation_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about author affiliations';

-- --------------------------------------------------------

--
-- Table structure for table `author_settings`
--

CREATE TABLE `author_settings` (
  `author_setting_id` bigint UNSIGNED NOT NULL,
  `author_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about authors, including localized properties such as their name and affiliation.';

--
-- Dumping data for table `author_settings`
--

INSERT INTO `author_settings` (`author_setting_id`, `author_id`, `locale`, `setting_name`, `setting_value`) VALUES
(17, 4, '', 'country', 'DE'),
(18, 4, 'en', 'familyName', 'Hancock'),
(19, 4, 'en', 'givenName', 'Peter J.B.'),
(21, 5, 'en', 'biography', NULL),
(22, 5, '', 'country', 'DE'),
(23, 5, 'en', 'familyName', 'Baddeley'),
(24, 5, 'en', 'givenName', 'Roland J.'),
(25, 5, 'en', 'preferredPublicName', NULL),
(26, 6, 'en', 'biography', NULL),
(27, 6, '', 'country', 'DE'),
(28, 6, 'en', 'familyName', 'Smith'),
(29, 6, 'en', 'givenName', 'Leslie S.'),
(30, 6, 'en', 'preferredPublicName', NULL),
(32, 7, '', 'country', 'DE'),
(33, 7, 'en', 'familyName', 'Lee'),
(34, 7, 'en', 'givenName', 'Terry J'),
(36, 8, 'en', 'biography', NULL),
(37, 8, '', 'country', 'DE'),
(38, 8, 'en', 'familyName', 'Suh'),
(39, 8, 'en', 'givenName', 'Erica'),
(40, 8, 'en', 'preferredPublicName', NULL),
(41, 9, 'en', 'biography', NULL),
(42, 9, '', 'country', 'DE'),
(43, 9, 'en', 'familyName', 'Hill'),
(44, 9, 'en', 'givenName', 'Kimball'),
(45, 9, 'en', 'preferredPublicName', NULL),
(46, 10, 'en', 'biography', NULL),
(47, 10, '', 'country', 'DE'),
(48, 10, 'en', 'familyName', 'Piccolo'),
(49, 10, 'en', 'givenName', 'Stephen R'),
(50, 10, 'en', 'preferredPublicName', NULL),
(52, 11, '', 'country', 'DE'),
(53, 11, 'en', 'familyName', 'Brunsdon'),
(54, 11, 'en', 'givenName', 'Chris'),
(56, 12, 'en', 'biography', NULL),
(57, 12, '', 'country', 'DE'),
(58, 12, 'en', 'familyName', 'Comber'),
(59, 12, 'en', 'givenName', 'Alexis'),
(60, 12, 'en', 'preferredPublicName', NULL),
(61, 13, 'en', 'biography', ''),
(62, 13, '', 'country', ''),
(63, 13, 'en', 'familyName', 'Nüst'),
(64, 13, 'en', 'givenName', 'Daniel'),
(65, 13, '', 'url', ''),
(72, 15, '', 'country', 'DE'),
(73, 15, 'en', 'familyName', 'Nuhn'),
(74, 15, 'en', 'givenName', 'Eva'),
(76, 16, 'en', 'biography', NULL),
(77, 16, '', 'country', 'DE'),
(78, 16, 'en', 'familyName', 'König'),
(79, 16, 'en', 'givenName', 'Franziska'),
(80, 16, 'en', 'preferredPublicName', NULL),
(81, 17, 'en', 'biography', NULL),
(82, 17, '', 'country', 'DE'),
(83, 17, 'en', 'familyName', 'Timpf'),
(84, 17, 'en', 'givenName', 'Sabine'),
(85, 17, 'en', 'preferredPublicName', NULL),
(87, 18, '', 'country', 'DE'),
(88, 18, 'en', 'familyName', 'Dong'),
(89, 18, 'en', 'givenName', 'Ruining'),
(91, 19, 'en', 'biography', NULL),
(92, 19, '', 'country', 'DE'),
(93, 19, 'en', 'familyName', 'Cameron'),
(94, 19, 'en', 'givenName', 'Daniel'),
(95, 19, 'en', 'preferredPublicName', NULL),
(96, 20, 'en', 'biography', NULL),
(97, 20, '', 'country', 'DE'),
(98, 20, 'en', 'familyName', 'Bedo'),
(99, 20, 'en', 'givenName', 'Justin'),
(100, 20, 'en', 'preferredPublicName', NULL),
(101, 21, 'en', 'biography', NULL),
(102, 21, '', 'country', 'DE'),
(103, 21, 'en', 'familyName', 'Papenfuss'),
(104, 21, 'en', 'givenName', 'Anthony T'),
(105, 21, 'en', 'preferredPublicName', NULL),
(107, 22, '', 'country', 'DE'),
(108, 22, 'en', 'familyName', 'Liu'),
(109, 22, 'en', 'givenName', 'Zilong'),
(111, 23, 'en', 'biography', NULL),
(112, 23, '', 'country', 'DE'),
(113, 23, 'en', 'familyName', 'Janowicz'),
(114, 23, 'en', 'givenName', 'Krzysztof'),
(115, 23, 'en', 'preferredPublicName', NULL),
(116, 24, 'en', 'biography', NULL),
(117, 24, '', 'country', 'CN'),
(118, 24, 'en', 'familyName', 'Cai'),
(119, 24, 'en', 'givenName', 'Ling'),
(120, 24, 'en', 'preferredPublicName', NULL),
(121, 25, 'en', 'biography', NULL),
(122, 25, '', 'country', 'CN'),
(123, 25, 'en', 'familyName', 'Zhu'),
(124, 25, 'en', 'givenName', 'Rui'),
(125, 25, 'en', 'preferredPublicName', NULL),
(126, 26, 'en', 'biography', NULL),
(127, 26, '', 'country', 'CN'),
(128, 26, 'en', 'familyName', 'Mai'),
(129, 26, 'en', 'givenName', 'Gengchen'),
(130, 26, 'en', 'preferredPublicName', NULL),
(131, 27, 'en', 'biography', NULL),
(132, 27, '', 'country', 'CN'),
(133, 27, 'en', 'familyName', 'Shi'),
(134, 27, 'en', 'givenName', 'Meilin'),
(135, 27, 'en', 'preferredPublicName', NULL),
(137, 28, '', 'country', 'DE'),
(138, 28, 'en', 'familyName', 'Nuhn'),
(139, 28, 'en', 'givenName', 'Eva'),
(141, 29, 'en', 'biography', NULL),
(142, 29, '', 'country', 'DE'),
(143, 29, 'en', 'familyName', 'Hamburger'),
(144, 29, 'en', 'givenName', 'Kai'),
(145, 29, 'en', 'preferredPublicName', NULL),
(146, 30, 'en', 'biography', NULL),
(147, 30, '', 'country', 'DE'),
(148, 30, 'en', 'familyName', 'Timpf'),
(149, 30, 'en', 'givenName', 'Sabine'),
(150, 30, 'en', 'preferredPublicName', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` bigint NOT NULL,
  `context_id` bigint NOT NULL,
  `parent_id` bigint DEFAULT NULL,
  `seq` bigint DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `image` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Categories permit the organization of submissions into a heirarchical structure.';

-- --------------------------------------------------------

--
-- Table structure for table `category_settings`
--

CREATE TABLE `category_settings` (
  `category_setting_id` bigint UNSIGNED NOT NULL,
  `category_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about categories, including localized properties such as names.';

-- --------------------------------------------------------

--
-- Table structure for table `citations`
--

CREATE TABLE `citations` (
  `citation_id` bigint NOT NULL,
  `publication_id` bigint NOT NULL,
  `raw_citation` text NOT NULL,
  `seq` bigint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A citation made by an associated publication.';

-- --------------------------------------------------------

--
-- Table structure for table `citation_settings`
--

CREATE TABLE `citation_settings` (
  `citation_setting_id` bigint UNSIGNED NOT NULL,
  `citation_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Additional data about citations, including localized content.';

-- --------------------------------------------------------

--
-- Table structure for table `codecheck_metadata`
--

CREATE TABLE `codecheck_metadata` (
  `submission_id` bigint NOT NULL,
  `version` varchar(50) NOT NULL DEFAULT 'latest',
  `publication_type` varchar(50) NOT NULL DEFAULT 'doi',
  `manifest` text,
  `repository` varchar(500) DEFAULT NULL,
  `source` text,
  `codecheckers` text,
  `certificate` varchar(100) DEFAULT NULL,
  `check_time` timestamp NULL DEFAULT NULL,
  `summary` text,
  `report` varchar(500) DEFAULT NULL,
  `additional_content` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `completed_payments`
--

CREATE TABLE `completed_payments` (
  `completed_payment_id` bigint NOT NULL,
  `timestamp` datetime NOT NULL,
  `payment_type` bigint NOT NULL,
  `context_id` bigint NOT NULL,
  `user_id` bigint DEFAULT NULL,
  `assoc_id` bigint DEFAULT NULL,
  `amount` decimal(8,2) UNSIGNED NOT NULL,
  `currency_code_alpha` varchar(3) DEFAULT NULL,
  `payment_method_plugin_name` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A list of completed (fulfilled) payments relating to a payment type such as a subscription payment.';

-- --------------------------------------------------------

--
-- Table structure for table `controlled_vocabs`
--

CREATE TABLE `controlled_vocabs` (
  `controlled_vocab_id` bigint NOT NULL,
  `symbolic` varchar(64) NOT NULL,
  `assoc_type` bigint NOT NULL DEFAULT '0',
  `assoc_id` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Every word or phrase used in a controlled vocabulary. Controlled vocabularies are used for submission metadata like keywords and subjects, reviewer interests, and wherever a similar dictionary of words or phrases is required. Each entry corresponds to a word or phrase like "cellular reproduction" and a type like "submissionKeyword".';

--
-- Dumping data for table `controlled_vocabs`
--

INSERT INTO `controlled_vocabs` (`controlled_vocab_id`, `symbolic`, `assoc_type`, `assoc_id`) VALUES
(1, 'interest', 0, NULL),
(9, 'submissionAgency', 1048588, 2),
(13, 'submissionAgency', 1048588, 3),
(17, 'submissionAgency', 1048588, 4),
(21, 'submissionAgency', 1048588, 5),
(29, 'submissionAgency', 1048588, 7),
(33, 'submissionAgency', 1048588, 8),
(37, 'submissionAgency', 1048588, 9),
(41, 'submissionAgency', 1048588, 10),
(8, 'submissionDiscipline', 1048588, 2),
(12, 'submissionDiscipline', 1048588, 3),
(16, 'submissionDiscipline', 1048588, 4),
(20, 'submissionDiscipline', 1048588, 5),
(28, 'submissionDiscipline', 1048588, 7),
(32, 'submissionDiscipline', 1048588, 8),
(36, 'submissionDiscipline', 1048588, 9),
(40, 'submissionDiscipline', 1048588, 10),
(6, 'submissionKeyword', 1048588, 2),
(10, 'submissionKeyword', 1048588, 3),
(14, 'submissionKeyword', 1048588, 4),
(18, 'submissionKeyword', 1048588, 5),
(26, 'submissionKeyword', 1048588, 7),
(30, 'submissionKeyword', 1048588, 8),
(34, 'submissionKeyword', 1048588, 9),
(38, 'submissionKeyword', 1048588, 10),
(7, 'submissionSubject', 1048588, 2),
(11, 'submissionSubject', 1048588, 3),
(15, 'submissionSubject', 1048588, 4),
(19, 'submissionSubject', 1048588, 5),
(27, 'submissionSubject', 1048588, 7),
(31, 'submissionSubject', 1048588, 8),
(35, 'submissionSubject', 1048588, 9),
(39, 'submissionSubject', 1048588, 10);

-- --------------------------------------------------------

--
-- Table structure for table `controlled_vocab_entries`
--

CREATE TABLE `controlled_vocab_entries` (
  `controlled_vocab_entry_id` bigint NOT NULL,
  `controlled_vocab_id` bigint NOT NULL,
  `seq` double DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='The order that a word or phrase used in a controlled vocabulary should appear. For example, the order of keywords in a publication.';

-- --------------------------------------------------------

--
-- Table structure for table `controlled_vocab_entry_settings`
--

CREATE TABLE `controlled_vocab_entry_settings` (
  `controlled_vocab_entry_setting_id` bigint UNSIGNED NOT NULL,
  `controlled_vocab_entry_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about a controlled vocabulary entry, including localized properties such as the actual word or phrase.';

-- --------------------------------------------------------

--
-- Table structure for table `custom_issue_orders`
--

CREATE TABLE `custom_issue_orders` (
  `custom_issue_order_id` bigint UNSIGNED NOT NULL,
  `issue_id` bigint NOT NULL,
  `journal_id` bigint NOT NULL,
  `seq` double NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Ordering information for the issue list, when custom issue ordering is specified.';

-- --------------------------------------------------------

--
-- Table structure for table `custom_section_orders`
--

CREATE TABLE `custom_section_orders` (
  `custom_section_order_id` bigint UNSIGNED NOT NULL,
  `issue_id` bigint NOT NULL,
  `section_id` bigint NOT NULL,
  `seq` double NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Ordering information for sections within issues, when issue-specific section ordering is specified.';

-- --------------------------------------------------------

--
-- Table structure for table `data_object_tombstones`
--

CREATE TABLE `data_object_tombstones` (
  `tombstone_id` bigint NOT NULL,
  `data_object_id` bigint NOT NULL,
  `date_deleted` datetime NOT NULL,
  `set_spec` varchar(255) NOT NULL,
  `set_name` varchar(255) NOT NULL,
  `oai_identifier` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Entries for published data that has been removed. Usually used in the OAI endpoint.';

-- --------------------------------------------------------

--
-- Table structure for table `data_object_tombstone_oai_set_objects`
--

CREATE TABLE `data_object_tombstone_oai_set_objects` (
  `object_id` bigint NOT NULL,
  `tombstone_id` bigint NOT NULL,
  `assoc_type` bigint NOT NULL,
  `assoc_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Relationships between tombstones and other data that can be collected in OAI sets, e.g. sections.';

-- --------------------------------------------------------

--
-- Table structure for table `data_object_tombstone_settings`
--

CREATE TABLE `data_object_tombstone_settings` (
  `tombstone_setting_id` bigint UNSIGNED NOT NULL,
  `tombstone_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL COMMENT '(bool|int|float|string|object)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about data object tombstones, including localized content.';

-- --------------------------------------------------------

--
-- Table structure for table `dois`
--

CREATE TABLE `dois` (
  `doi_id` bigint NOT NULL,
  `context_id` bigint NOT NULL,
  `doi` varchar(255) NOT NULL,
  `status` smallint NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Stores all DOIs used in the system.';

-- --------------------------------------------------------

--
-- Table structure for table `doi_settings`
--

CREATE TABLE `doi_settings` (
  `doi_setting_id` bigint UNSIGNED NOT NULL,
  `doi_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about DOIs, including the registration agency.';

-- --------------------------------------------------------

--
-- Table structure for table `edit_decisions`
--

CREATE TABLE `edit_decisions` (
  `edit_decision_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `review_round_id` bigint DEFAULT NULL,
  `stage_id` bigint DEFAULT NULL,
  `round` smallint DEFAULT NULL,
  `editor_id` bigint NOT NULL,
  `decision` smallint NOT NULL COMMENT 'A numeric constant indicating the decision that was taken. Possible values are listed in the Decision class.',
  `date_decided` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Editorial decisions recorded on a submission, such as decisions to accept or decline the submission, as well as decisions to send for review, send to copyediting, request revisions, and more.';

--
-- Dumping data for table `edit_decisions`
--

INSERT INTO `edit_decisions` (`edit_decision_id`, `submission_id`, `review_round_id`, `stage_id`, `round`, `editor_id`, `decision`, `date_decided`) VALUES
(2, 2, NULL, 1, NULL, 5, 17, '2026-02-26 02:24:16'),
(3, 3, NULL, 1, NULL, 5, 17, '2026-02-26 02:26:42'),
(4, 4, NULL, 1, NULL, 5, 17, '2026-02-26 02:27:23'),
(5, 5, NULL, 1, NULL, 5, 17, '2026-02-26 02:29:14'),
(6, 7, NULL, 1, NULL, 5, 17, '2026-02-26 02:29:42'),
(7, 8, NULL, 1, NULL, 5, 3, '2026-02-26 02:31:37'),
(8, 9, NULL, 1, NULL, 5, 3, '2026-02-26 02:32:10');

-- --------------------------------------------------------

--
-- Table structure for table `email_log`
--

CREATE TABLE `email_log` (
  `log_id` bigint NOT NULL,
  `assoc_type` bigint NOT NULL,
  `assoc_id` bigint NOT NULL,
  `sender_id` bigint DEFAULT NULL,
  `date_sent` datetime NOT NULL,
  `event_type` bigint DEFAULT NULL,
  `from_address` varchar(255) DEFAULT NULL,
  `recipients` text,
  `cc_recipients` text,
  `bcc_recipients` text,
  `subject` varchar(255) DEFAULT NULL,
  `body` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A record of email messages that are sent in relation to an associated entity, such as a submission.';

-- --------------------------------------------------------

--
-- Table structure for table `email_log_users`
--

CREATE TABLE `email_log_users` (
  `email_log_user_id` bigint UNSIGNED NOT NULL,
  `email_log_id` bigint NOT NULL,
  `user_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A record of users associated with an email log entry.';

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `email_id` bigint NOT NULL,
  `email_key` varchar(255) NOT NULL COMMENT 'Unique identifier for this email.',
  `context_id` bigint NOT NULL,
  `alternate_to` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Custom email templates created by each context, and overrides of the default templates.';

--
-- Dumping data for table `email_templates`
--

INSERT INTO `email_templates` (`email_id`, `email_key`, `context_id`, `alternate_to`) VALUES
(1, 'COPYEDIT_REQUEST', 1, 'DISCUSSION_NOTIFICATION_COPYEDITING'),
(2, 'EDITOR_ASSIGN_SUBMISSION', 1, 'DISCUSSION_NOTIFICATION_SUBMISSION'),
(3, 'EDITOR_ASSIGN_REVIEW', 1, 'DISCUSSION_NOTIFICATION_REVIEW'),
(4, 'EDITOR_ASSIGN_PRODUCTION', 1, 'DISCUSSION_NOTIFICATION_PRODUCTION'),
(5, 'LAYOUT_REQUEST', 1, 'DISCUSSION_NOTIFICATION_PRODUCTION'),
(6, 'LAYOUT_COMPLETE', 1, 'DISCUSSION_NOTIFICATION_PRODUCTION');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates_default_data`
--

CREATE TABLE `email_templates_default_data` (
  `email_templates_default_data_id` bigint UNSIGNED NOT NULL,
  `email_key` varchar(255) NOT NULL COMMENT 'Unique identifier for this email.',
  `locale` varchar(28) NOT NULL DEFAULT 'en',
  `name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Default email templates created for every installed locale.';

--
-- Dumping data for table `email_templates_default_data`
--

INSERT INTO `email_templates_default_data` (`email_templates_default_data_id`, `email_key`, `locale`, `name`, `subject`, `body`) VALUES
(1, 'PASSWORD_RESET_CONFIRM', 'de', 'Bestätigung der Passwortzurücksetzung', 'Bestätigung der Passwortzurücksetzung', 'Wir wurden aufgefordert, Ihr Passwort für die Webseite {$siteTitle} neu zu setzen.<br />\n<br />\nFalls die Aufforderung nicht von Ihnen stammt, ignorieren Sie bitte diese E-Mail und Ihr Passwort bleibt unverändert. Falls Sie Ihr Passwort neu setzen möchten, klicken Sie bitte auf die folgende URL:<br />\n<br />\nMein Passwort neu setzen: {$passwordResetUrl}<br />\n<br />\n{$siteContactName}'),
(2, 'PASSWORD_RESET_CONFIRM', 'en', 'Password Reset Confirm', 'Password Reset Confirmation', 'We have received a request to reset your password for the {$siteTitle} web site.<br />\n<br />\nIf you did not make this request, please ignore this email and your password will not be changed. If you wish to reset your password, click on the below URL.<br />\n<br />\nReset my password: {$passwordResetUrl}<br />\n<br />\n{$siteContactName}'),
(3, 'USER_REGISTER', 'de', 'Benutzer/in angelegt', 'Registrierung bei der Zeitschrift', '{$recipientName}<br />\n<br />\nSie sind nun als neue/r Benutzer/in von {$contextName} registriert. Wir haben Ihren Benutzer/innennamen und Ihr Passwort in dieser Mail aufgeführt, beides wird für alle Arbeiten mit dieser Zeitschrift gebraucht. Sie können sich zu jedem Zeitpunkt als Benutzer/in der Zeitschrift austragen lassen, indem Sie mich kontaktieren.<br />\n<br />\nBenutzer/innenname: {$recipientUsername}<br />\nPasswort: {$password}<br />\n<br />\nVielen Dank <br />\n{$signature}'),
(4, 'USER_REGISTER', 'en', 'User Created', 'Journal Registration', '{$recipientName}<br />\n<br />\nYou have now been registered as a user with {$contextName}. We have included your username and password in this email, which are needed for all work with this journal through its website. At any point, you can ask to be removed from the journal\'s list of users by contacting me.<br />\n<br />\nUsername: {$recipientUsername}<br />\nPassword: {$password}<br />\n<br />\nThank you,<br />\n{$signature}'),
(5, 'USER_VALIDATE_CONTEXT', 'de', 'E-Mail überprüfen (Zeitschrift Registrierung)', 'Account validieren', '{$recipientName}<br />\n<br />\nSie haben ein Benutzer/innenkonto bei {$contextName} angelegt, aber bevor Sie es benutzen können, müssen Sie Ihre E-Mail-Adresse bestätigen. Dazu folgen Sie bitte einfach dem folgenden Link:<br />\n<br />\n{$activateUrl}<br />\n<br />\nVielen Dank<br />\n{$contextSignature}'),
(6, 'USER_VALIDATE_CONTEXT', 'en', 'Validate Email (Journal Registration)', 'Validate Your Account', '{$recipientName}<br />\n<br />\nYou have created an account with {$contextName}, but before you can start using it, you need to validate your email account. To do this, simply follow the link below:<br />\n<br />\n{$activateUrl}<br />\n<br />\nThank you,<br />\n{$contextSignature}'),
(7, 'USER_VALIDATE_SITE', 'de', 'E-Mail überprüfen (Seite)', 'Account validieren', '{$recipientName}<br />\n<br />\nSie haben ein Benutzer/innenkonto bei {$siteTitle} angelegt, aber bevor Sie es benutzen können, müssen Sie Ihre E-Mail-Adresse bestätigen. Dazu folgen Sie bitte einfach dem folgenden Link:<br />\n<br />\n{$activateUrl}<br />\n<br />\nVielen Dank<br />\n{$siteSignature}'),
(8, 'USER_VALIDATE_SITE', 'en', 'Validate Email (Site)', 'Validate Your Account', '{$recipientName}<br />\n<br />\nYou have created an account with {$siteTitle}, but before you can start using it, you need to validate your email account. To do this, simply follow the link below:<br />\n<br />\n{$activateUrl}<br />\n<br />\nThank you,<br />\n{$siteSignature}'),
(9, 'REVIEWER_REGISTER', 'de', 'Gutachter/in registriert', 'Registrierung als Benutzer/in bei {$contextName}', '<p>Guten Tag {$recipientName}, </p><p>angesichts Ihrer Expertise haben wir uns erlaubt, Ihren Namen der Gutachter/innendatenbank von {$contextName} hinzuzufügen. Dies verpflichtet Sie zu nichts, ermöglicht uns aber, Sie um mögliche Gutachten für eine Einreichung zu bitten. Wenn Sie zu einem Gutachten eingeladen werden, werden Sie Titel und Abstract des Beitrags sehen können und werden stets selber entscheiden können, ob Sie der Einladung folgen oder nicht. Sie können zu jedem Zeitpunkt Ihren Namen von der Gutachter/innenliste entfernen lassen.</p><p>Wir senden Ihnen einen Benutzer/innennamen und ein Passwort, die Sie in allen Schritten der Zusammenarbeit mit der Zeitschrift über deren Website benötigen. Vielleicht möchten Sie z.B. Ihr Profil inkl. Ihrer Begutachtungsinteressen aktualisieren.</p><p>Benutzer/innenname: {$recipientUsername}<br />Passwort: {$password}</p><p>Vielen Dank</p>{$signature}'),
(10, 'REVIEWER_REGISTER', 'en', 'Reviewer Register', 'Registration as Reviewer with {$contextName}', '<p>Dear {$recipientName},</p><p>In light of your expertise, we have registered your name in the reviewer database for {$contextName}. This does not entail any form of commitment on your part, but simply enables us to approach you with a submission to possibly review. On being invited to review, you will have an opportunity to see the title and abstract of the paper in question, and you\'ll always be in a position to accept or decline the invitation. You can also ask at any point to have your name removed from this reviewer list.</p><p>We are providing you with a username and password, which is used in all interactions with the journal through its website. You may wish, for example, to update your profile, including your reviewing interests.</p><p>Username: {$recipientUsername}<br />Password: {$password}</p><p>Thank you,</p>{$signature}'),
(11, 'ISSUE_PUBLISH_NOTIFY', 'de', 'Ausgabe veröffentlicht Benachrichtigung', 'Gerade veröffentlicht: {$issueIdentification} von {$contextName}', '<p>Guten Tag {$recipientName},</p><p> wir freuen uns Ihnen mitteilen zu können, dass eine neue Ausgabe von {$contextName} veröffentlicht wurde: <a href=\"{$issueUrl}\">{$issueIdentification}</a>. Wir laden Sie dazu ein, die Ausgabe zu lesen und mit Anderen Ihrer wissenschaftlichen Community zu teilen.</p><p>Vielen Dank an unsere Autor/innen, Gutachter/innen und Redakteur/innen für ihre wertvollen Beiträge - und natürlich an unsere Leser/innen für das anhaltende Interesse an unserer Arbeit.</p><div>{$issueToc}</div><p>Vielen Dank</p>{$signature}'),
(12, 'ISSUE_PUBLISH_NOTIFY', 'en', 'Issue Published Notify', 'Just published: {$issueIdentification} of {$contextName}', '<p>Dear {$recipientName},</p><p>We are pleased to announce the publication of <a href=\"{$issueUrl}\">{$issueIdentification}</a> of {$contextName}.  We invite you to read and share this work with your scholarly community.</p><p>Many thanks to our authors, reviewers, and editors for their valuable contributions, and to our readers for your continued interest.</p><div>{$issueToc}</div><p>Thank you,</p>{$signature}'),
(13, 'SUBMISSION_ACK', 'de', 'Bestätigung der Einreichung', 'Ihre Einreichung für {$contextName}: Eingangsbestätigung', '<p>Sehr geehrte/r {$recipientName},</p><p>vielen Dank für die Einreichung Ihres Manuskripts &quot;{$submissionTitle}&quot; zur Veröffentlichung in {$contextName}. Ein Mitglied unseres Redaktionsteams wird sie sich bald ansehen. Sie erhalten eine E-Mail, wenn eine erste Entscheidung getroffen wurde, und wir können Sie auch für weitere Informationen kontaktieren.</p><p>Das Verwaltungssystem unserer Webzeitschrift erlaubt Ihnen, jederzeit den Lauf Ihres Beitrags im Redaktionsprozess zu beobachten. Sie loggen sich dazu einfach auf unserer Webseite ein:</p><p>URL des Manuskripts: {$submissionUrl}</p><p>Benutzer/innenname: {$recipientUsername}</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=\"{$authorSubmissionUrl}\">Dashboard Ihrer Einreichung</a>.</p><p>Danke für Ihr Interesse an einer Veröffentlichung in unserer Zeitschrift {$contextName} .</p>{$contextSignature}'),
(14, 'SUBMISSION_ACK', 'en', 'Submission Confirmation', 'Thank you for your submission to {$contextName}', '<p>Dear {$recipientName},</p><p>Thank you for your submission to {$contextName}. We have received your submission, \"{$submissionTitle}\", and a member of our editorial team will see it soon. You will be sent an email when an initial decision is made, and we may contact you for further information.</p><p>You can view your submission and track its progress through the editorial process at the following location:</p><p>Submission URL: {$authorSubmissionUrl}</p><p>If you have been logged out, you can login again with the username {$recipientUsername}.</p><p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p><p>Thank you for considering {$contextName} as a venue for your work.</p>{$contextSignature}'),
(15, 'SUBMISSION_ACK_NOT_USER', 'de', 'Bestätigung der Einreichung (andere Autor/innen)', 'Eingangsbestätigung', '<p>Sehr geehrte/r {$recipientName},</p><p>Sie wurden als Mitautor/in bei einer Einreichung bei {$KontextName} genannt. Der Verfasser/die Verfasserin, {$Einreichername}, hat die folgenden Angaben gemacht:</p><p>{$submissionTitle}<br>{$authorsWithAffiliation}</p><p>Wenn eine dieser Angaben nicht korrekt ist oder Sie nicht in diesem Beitrag genannt werden möchten, setzen Sie sich bitte mit mir in Verbindung.</p><p>Vielen Dank, dass Sie {$contextName} als Plattform für Ihre Arbeit in Betracht ziehen.</p><p>Mit freundlichen Grüßen,</p>{$contextSignature}'),
(16, 'SUBMISSION_ACK_NOT_USER', 'en', 'Submission Confirmation (Other Authors)', 'Submission confirmation', '<p>Dear {$recipientName},</p><p>You have been named as a co-author on a submission to {$contextName}. The submitter, {$submitterName}, provided the following details:</p><p>\"{$submissionTitle}\"<br>{$authorsWithAffiliation}</p><p>If any of these details are incorrect, or you do not wish to be named on this submission, please contact me.</p><p>Thank you for considering {$contextName} as a venue for your work.</p><p>Kind regards,</p>{$contextSignature}'),
(17, 'EDITOR_ASSIGN', 'de', 'Redakteur/in zugewiesen', 'Sie wurden als Redakteur/in von einer Einreichung bei {$contextName} zugewiesen', '<p>Sehr geehrte/r {$recipientName},</p><p>Die folgende Einreichung wurde Ihnen zur redaktionellen Bearbeitung zugewiesen. </p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>Wenn Sie der Meinung sind, dass die Einreichung für die Zeitschrift {$contextName} relevant ist, leiten Sie die Einreichung bitte zur Begutachtung weiter, indem Sie \"In die Begutachtung schicken\" wählen und dann Gutachter zuweisen, indem Sie auf \"Gutachter/in hinzufügen\" klicken. </p><p>Wenn die Einreichung nicht für diese Zeitschrift geeignet ist, lehnen Sie die Einreichung bitte ab.</p><p>Danke im Voraus.</p><p>Mit freundlichen Grüßen,</p>{$contextSignature}'),
(18, 'EDITOR_ASSIGN', 'en', 'Editor Assigned', 'You have been assigned as an editor on a submission to {$contextName}', '<p>Dear {$recipientName},</p><p>The following submission has been assigned to you to see through the editorial process.</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>If you find the submission to be relevant for {$contextName}, please forward the submission to the review stage by selecting \"Send to Review\" and then assign reviewers by clicking \"Add Reviewer\".</p><p>If the submission is not appropriate for this journal, please decline the submission.</p><p>Thank you in advance.</p><p>Kind regards,</p>{$contextSignature}'),
(19, 'REVIEW_CANCEL', 'de', 'Gutachter/in nicht mehr zugeordnet', 'Anfrage zur Begutachtung zurückgezogen', '<p>Sehr geehrte/r {$recipientName},</p><p>vor kurzem haben wir Sie gebeten, eine Einsendung an die Zeitschrift{$KontextName} zu begutachten. Wir haben uns entschlossen, die Bitte um Begutachtung der Einreichung {$submissionTitle} zu stornieren.</p><p>Wir entschuldigen uns für alle Unannehmlichkeiten, die Ihnen dadurch entstehen, und hoffen, dass wir Sie auch in Zukunft zur Unterstützung des Begutachtungsprozesses dieser Zeitschrift heranziehen können.</p><p>Wenn Sie irgendwelche Fragen haben, wenden Sie sich bitte an mich.</p>{$signature}'),
(20, 'REVIEW_CANCEL', 'en', 'Reviewer Unassign', 'Request for Review Cancelled', '<p>Dear {$recipientName},</p><p>Recently, we asked you to review a submission to {$contextName}. We have decided to cancel the request for you to reivew the submission, {$submissionTitle}.</p><p>We apologize any inconvenience this may cause you and hope that we will be able to call on you to assist with this journal\'s review process in the future.</p><p>If you have any questions, please contact me.</p>{$signature}'),
(21, 'REVIEW_REINSTATE', 'de', 'Gutachter/in wieder eingesetzt', 'Können Sie weiterhin einen Beitrag für {$contextName} begutachten?', '<p>Sehr geehrte/r {$recipientName},</p><p>wir haben vor kurzem unsere Anfrage an Sie, eine Einreichung, {$submissionTitle}, für die Zeitschrift {$contextName} zu begutachten, storniert. Wir haben diese Entscheidung rückgängig gemacht und hoffen, dass Sie immer noch in der Lage sind, die Begutachtung durchzuführen.</p><p>Wenn Sie in der Lage sind, bei der Begutachtung dieser Einreichung zu helfen, können Sie sich <a href=\"{$reviewAssignmentUrl}\">bei der Zeitschrift anmelden</a>, um die Einreichung zu sehen, Begutachtungsdateien hochzuladen und Ihre Begutachtungsanfrage zu übermitteln.</p><p>Wenn Sie irgendwelche Fragen haben, kontaktieren Sie mich bitte.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(22, 'REVIEW_REINSTATE', 'en', 'Reviewer Reinstate', 'Can you still review something for {$contextName}?', '<p>Dear {$recipientName},</p><p>We recently cancelled our request for you to review a submission, {$submissionTitle}, for {$contextName}. We\'ve reversed that decision and we hope that you are still able to conduct the review.</p><p>If you are able to assist with this submission\'s review, you can <a href=\"{$reviewAssignmentUrl}\">login to the journal</a> to view the submission, upload review files, and submit your review request.</p><p>If you have any questions, please contact me.</p><p>Kind regards,</p>{$signature}'),
(23, 'REVIEW_RESEND_REQUEST', 'de', 'Gutachtenanfrage erneut an Gutachter/in senden', 'Erneute Anfrage nach Ihrem Gutachten für {$contextName}', '<p>Sehr geehrte/r {$recipientName},</p><p>Kürzlich haben Sie unsere Bitte abgelehnt, eine Einreichung, {$submissionTitle}, für {$contextName} zu prüfen. Ich schreibe Ihnen, um zu erfahren, ob Sie die Prüfung doch noch durchführen können.</p><p>Wir wären Ihnen dankbar, wenn Sie diese Beurteilung vornehmen könnten, haben aber Verständnis dafür, wenn dies zum jetzigen Zeitpunkt nicht möglich ist. In jedem Fall bitten wir Sie, <a href=\"{$reviewAssignmentUrl}\">die Anfrage</a> bis zum {$responseDueDate} anzunehmen oder abzulehnen, damit wir einen alternativen Gutachter oder eine alternative Gutachterin finden können.</p><p>Wenn Sie Fragen haben, wenden Sie sich bitte an mich.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(24, 'REVIEW_RESEND_REQUEST', 'en', 'Resend Review Request to Reviewer', 'Requesting your review again for {$contextName}', '<p>Dear {$recipientName},</p><p>Recently, you declined our request to review a submission, \"{$submissionTitle}\", for {$contextName}. I\'m writing to see if you are able to conduct the review after all.</p><p>We would be grateful if you\'re able to perform this review, but we understand if that is not possible at this time. Either way, please <a href=\"{$reviewAssignmentUrl}\">accept or decline the request</a> by {$responseDueDate}, so that we can find an alternate reviewer.</p><p>If you have any questions, please contact me.</p><p>Kind regards,</p>{$signature}'),
(25, 'REVIEW_REQUEST', 'de', 'Gutachtenanfrage', 'Einladung zur Begutachtung', '<p>Sehr geehrte/r {$recipientName},</p><p>Ich glaube, dass Sie ein hervorragender Gutachter für einen Beitrag bei der Zeitschrift {$contextName} sein könnten. Ich hoffe, dass Sie diese wichtige Aufgabe für uns übernehmen werden.</p><p>Wenn Sie in der Lage sind, diesen Beitrag zu begutachten, ist Ihre Begutachtung bis zum {$reviewDueDate} fällig. Sie können die Einreichung einsehen, Begutachtungsdateien hochladen und Ihre Begutachtung einreichen, indem Sie sich auf der Website der Zeitschrift anmelden und den Schritten unter dem unten stehenden Link folgen. </p><p><a href=\"{$reviewAssignmentUrl}\">{$submissionTitle}</a></p><p><b>Abstract</b></p>{$submissionAbstract}<p>Ich erbitte die <a href=\"{$reviewAssignmentUrl}\">Annahme oder Ablehnung</a> des Gutachtens bis <b>{$responseDueDate}</b>. </p><p>Gerne stehe ich Ihnen zur Verfügung, wenn Sie Fragen zur Einreichung oder zum Begutachtungsverfahren haben.</p><p>Danke, dass Sie diesen Anfrage berücksichtigen. Ihre Hilfe wird sehr geschätzt.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(26, 'REVIEW_REQUEST', 'en', 'Review Request', 'Invitation to review', '<p>Dear {$recipientName},</p><p>I believe that you would serve as an excellent reviewer for a submission  to {$contextName}. The submission\'s title and abstract are below, and I hope that you will consider undertaking this important task for us.</p><p>If you are able to review this submission, your review is due by {$reviewDueDate}. You can view the submission, upload review files, and submit your review by logging into the journal site and following the steps at the link below.</p><p><a href=\"{$reviewAssignmentUrl}\">{$submissionTitle}</a></p><p><b>Abstract</b></p>{$submissionAbstract}<p>Please <a href=\"{$reviewAssignmentUrl}\">accept or decline</a> the review by <b>{$responseDueDate}</b>.</p><p>You may contact me with any questions about the submission or the review process.</p><p>Thank you for considering this request. Your help is much appreciated.</p><p>Kind regards,</p>{$signature}'),
(27, 'REVIEW_REQUEST_SUBSEQUENT', 'de', 'Nachfolgende Gutachtenanfrage', 'Anfrage zur Begutachtung einer überarbeiteten Einreichung', '<p>Sehr geehrte/r {$recipientName},</p><p>Danke für Ihr Gutachten zu <a href=\"{$reviewAssignmentUrl}\">{$submissionTitle}</a>. Die Autor/innen haben das Feedback der Gutachter/innen berücksichtigt und nun eine überarbeitete Version ihrer Arbeit eingereicht. Ich schreibe Ihnen, um Sie zu fragen, ob Sie eine zweite Begutachtungsrunde für diese Einreichung durchführen würden.</p><p>Wenn Sie in der Lage sind, diese Einreichung zu begutachten, ist Ihre Begutachtung bis {$reviewDueDate} fällig. Sie können <a href=\"{$reviewAssignmentUrl}\">den Begutachtungsschritten folgen</a>, um die Einreichung anzusehen, Begutachtungsdateien hochzuladen und Ihre Begutachtungskommentare einzureichen. <p><p><a href=\"{$reviewAssignmentUrl}\">{$submissionTitle}</a></p><p><b>Abstract</b></p>{$submissionAbstract}<p>Bitte <a href=\"{$reviewAssignmentUrl}\">Annahme oder Ablehnung</a> des Gutachtens bis zum <b>{$responseDueDate}</b>. </p><p>Bei Fragen zur Einreichung oder zum Begutachtungsverfahren können Sie sich gerne an mich wenden.</p><p>Danke, dass Sie diesen Anfrage berücksichtigen. Ihre Hilfe wird sehr geschätzt.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(28, 'REVIEW_REQUEST_SUBSEQUENT', 'en', 'Review Request Subsequent', 'Request to review a revised submission', '<p>Dear {$recipientName},</p><p>Thank you for your review of <a href=\"{$reviewAssignmentUrl}\">{$submissionTitle}</a>. The authors have considered the reviewers\' feedback and have now submitted a revised version of their work. I\'m writing to ask if you would conduct a second round of peer review for this submission.</p><p>If you are able to review this submission, your review is due by {$reviewDueDate}. You can <a href=\"{$reviewAssignmentUrl}\">follow the review steps</a> to view the submission, upload review files, and submit your review comments.<p><p><a href=\"{$reviewAssignmentUrl}\">{$submissionTitle}</a></p><p><b>Abstract</b></p>{$submissionAbstract}<p>Please <a href=\"{$reviewAssignmentUrl}\">accept or decline</a> the review by <b>{$responseDueDate}</b>.</p><p>Please feel free to contact me with any questions about the submission or the review process.</p><p>Thank you for considering this request. Your help is much appreciated.</p><p>Kind regards,</p>{$signature}'),
(29, 'REVIEW_RESPONSE_OVERDUE_AUTO', 'de', 'Antwort auf eine Gutachtenanfrage fällig (Automatisiert)', 'Können Sie den Beitrag für uns begutachten?', '<p>Sehr geehrte/r {$recipientName},</p><p>Diese E-Mail ist eine automatische Erinnerung der Zeitschrift {$Kontextname} in Bezug auf unsere Aufforderung zur Begutachtung der Einreichung \"{$submissionTitle}\".</p><p>Sie erhalten diese E-Mail, weil wir noch keine Bestätigung von Ihnen erhalten haben, ob Sie in der Lage sind, diese Einreichung zu begutachen oder nicht. </p><p>Bitte teilen Sie uns mit, ob Sie in der Lage sind, diesen Beitrag zu prüfen, indem Sie unsere Software zur Verwaltung der Einreichungen verwenden, um diese Anfrage anzunehmen oder abzulehnen.</p><p>Wenn Sie in der Lage sind, diesen Beitrag zu prüfen, ist Ihr Gutachten bis zum {$reviewDueDate} fällig. Sie können die Begutachtungsschritte befolgen, um die Einreichung einzusehen, Begutachtungsdateien hochzuladen und Ihre Begutachtungskommentare zu übermitteln.</p><p><a href=\"{$reviewAssignmentUrl}\">{$submissionTitle}</a></p><p><b>Abstract</b></p>{$submissionAbstract}<p>Bei Fragen zur Einreichung oder zum Begutachtungsprozess können Sie sich gerne an mich wenden.</p><p>Danke, dass Sie diese Anfrage berücksichtigen. Ihre Hilfe ist mir sehr willkommen.</p><p>Mit freundlichen Grüßen,</p>{$contextSignature}'),
(30, 'REVIEW_RESPONSE_OVERDUE_AUTO', 'en', 'Review Response Overdue (Automated)', 'Will you be able to review this for us?', '<p>Dear {$recipientName},</p><p>This email is an automated reminder from {$contextName} in regards to our request for your review of the submission, \"{$submissionTitle}.\"</p><p>You are receiving this email because we have not yet received a confirmation from you indicating whether or not you are able to undertake the review of this submission.</p><p>Please let us know whether or not you are able to undertake this review by using our submission management software to accept or decline this request.</p><p>If you are able to review this submission, your review is due by {$reviewDueDate}. You can follow the review steps to view the submission, upload review files, and submit your review comments.</p><p><a href=\"{$reviewAssignmentUrl}\">{$submissionTitle}</a></p><p><b>Abstract</b></p>{$submissionAbstract}<p>Please feel free to contact me with any questions about the submission or the review process.</p><p>Thank you for considering this request. Your help is much appreciated.</p><p>Kind regards,</p>{$contextSignature}'),
(31, 'REVIEW_CONFIRM', 'de', 'Gutachten bestätigt', 'Überprüfung angenommen: {$reviewerName} hat den Überprüfungsauftrag angenommen für #{$submissionId} {$authorsShort} — {$submissionTitle}', '<p>Sehr geehrte/r {$recipientName},</p><p>{$reviewerName} hat die folgende Überprüfung akzeptiert:</p><p><a href=\"{$submissionUrl}\">#{$submissionId} {$authorsShort} — {$submissionTitle}</a><br /><b>Gutachtenmethode:</b> {$reviewMethod}</p><p><b>Gutachten fällig am:</b> {$reviewDueDate}</p><p>Melden Sie sich an, um <a href=\"{$submissionUrl}\">alle Gutachteraufgaben</a> für diese Einreichung zu sehen.</p><br><br>—<br>Dies ist eine automatische Nachricht von <a href=\"{$contextUrl}\">{$contextName}</a>.'),
(32, 'REVIEW_CONFIRM', 'en', 'Review Confirm', 'Review accepted: {$reviewerName} accepted review assignment for #{$submissionId} {$authorsShort} — \"{$submissionTitle}\"', '<p>Dear {$recipientName},</p><p>{$reviewerName} has accepted the following review:</p><p><a href=\"{$submissionUrl}\">#{$submissionId} {$authorsShort} — \"{$submissionTitle}\"</a><br /><b>Type:</b> {$reviewMethod}</p><p><b>Review Due:</b> {$reviewDueDate}</p><p>Login to <a href=\"{$submissionUrl}\">view all reviewer assignments</a> for this submission.</p><br><br>—<br>This is an automated message from <a href=\"{$contextUrl}\">{$contextName}</a>.'),
(33, 'REVIEW_DECLINE', 'de', 'Gutachten abgelehnt', 'Nicht in der Lage zu begutachten', 'Sehr geehrte Redakteure/innen:<br />\n<br />\nIch fürchte, dass ich die Einreichung \"{$submissionTitle}\" für die Zeitschrift {$contextName} zur Zeit nicht begutachten kann. Vielen Dank, dass Sie an mich gedacht haben. Sie können mich gerne ein anderes Mal kontaktieren.<br />\n<br />\n{$Absendername}'),
(34, 'REVIEW_DECLINE', 'en', 'Review Decline', 'Unable to Review', 'Editors:<br />\n<br />\nI am afraid that at this time I am unable to review the submission, &quot;{$submissionTitle},&quot; for {$contextName}. Thank you for thinking of me, and another time feel free to call on me.<br />\n<br />\n{$senderName}'),
(35, 'REVIEW_ACK', 'de', 'Gutachten erhalten', 'Eingangsbestätigung für Ihr Gutachten', '<p>Sehr geehrte/r {$recipientName},</p>\n<p>vielen Dank für Ihr Gutachten zum Beitrag &quot;{$submissionTitle}&quot; für die Zeitschrift {$contextName}. Ihre Stellungnahme ist eine wichtige Unterstützung für unsere Bemühungen um die Qualität der von uns veröffentlichten Arbeiten.</p>\n<p>Es war uns eine Freude, mit Ihnen als Gutachter/in für {$contextName} zusammenzuarbeiten, und wir hoffen, dass wir auch in Zukunft die Gelegenheit haben werden, mit Ihnen zusammenzuarbeiten.</p>\n<p>Mit freundlichen Grüßen,</p>\n<p>{$Unterschrift}</p>'),
(36, 'REVIEW_ACK', 'en', 'Review Acknowledgement', 'Thank you for your review', '<p>Dear {$recipientName},</p>\n<p>Thank you for completing your review of the submission, \"{$submissionTitle}\", for {$contextName}. We appreciate your time and expertise in contributing to the quality of the work that we publish.</p>\n<p>It has been a pleasure to work with you as a reviewer for {$contextName}, and we hope to have the opportunity to work with you again in the future.</p>\n<p>Kind regards,</p>\n<p>{$signature}</p>'),
(37, 'REVIEW_REMIND', 'de', 'Erinnerung an das Gutachten', 'Eine Erinnerung, Ihr Gutachten bitte abzuschließen', '<p>Sehr geehrte/r {$recipientName},</p><p>nur eine freundliche Erinnerung an unsere Bitte um Ihre Begutachtung der Einreichung \"{$submissionTitle}\" für die Zeitschrift {$contextName}. Wir haben erwartet, dass wir diese Begutachtung bis zum {$reviewDueDate} erhalten, und würden uns freuen, sie zu bekommen, sobald Sie in der Lage sind, sie vorzubereiten.</p><p>Sie können sich <a href=\"{$reviewAssignmentUrl}\">bei der Zeitschrift anmelden</a> und den Begutachtungsschritten folgen, um die Einreichung zu sehen, Begutachtungsdateien hochzuladen und Ihre Begutachtungskommentare einzureichen.</p><p>Wenn Sie eine Verlängerung der Frist benötigen, kontaktieren Sie mich bitte. Ich freue mich darauf, von Ihnen zu hören.</p><p>Vielen Dank im Voraus und mit freundlichen Grüßen,</p>{$signature}'),
(38, 'REVIEW_REMIND', 'en', 'Review Reminder', 'A reminder to please complete your review', '<p>Dear {$recipientName},</p><p>Just a gentle reminder of our request for your review of the submission, \"{$submissionTitle},\" for {$contextName}. We were expecting to have this review by {$reviewDueDate} and we would be pleased to receive it as soon as you are able to prepare it.</p><p>You can <a href=\"{$reviewAssignmentUrl}\">login to the journal</a> and follow the review steps to view the submission, upload review files, and submit your review comments.</p><p>If you need an extension of the deadline, please contact me. I look forward to hearing from you.</p><p>Thank you in advance and kind regards,</p>{$signature}'),
(39, 'REVIEW_REMIND_AUTO', 'de', 'Erinnerung an das Gutachten (Automatisiert)', 'Eine Erinnerung, Ihr Gutachten bitte abzuschließen', '<p>Sehr geehrte/r {$recipientName}</p><p>Diese E-Mail ist eine automatische Erinnerung von {$contextName} in Bezug auf unsere Bitte um Ihre Begutachtung der Einreichung \"{$submissionTitle}\"</p><p>Wir haben Ihr Gutachten bis zum {$reviewDueDate} erwartet und würden uns freuen, es zu erhalten, sobald Sie es vorbereiten können. </p><p>Bitte <a href=\"{$reviewAssignmentUrl}\">melden Sie sich bei der Zeitschrift an</a> und folgen Sie den Begutachtungsschritten, um die Einreichung einzusehen, die Begutachtungsdateien hochzuladen und Ihre Begutachtungskommentare abzugeben.</p><p>Wenn Sie eine Verlängerung der Frist benötigen, setzen Sie sich bitte mit mir in Verbindung. Ich freue mich darauf, von Ihnen zu hören.</p><p>Vielen Dank im Voraus und mit freundlichen Grüßen,</p>{$contextSignature}'),
(40, 'REVIEW_REMIND_AUTO', 'en', 'Review Reminder (Automated)', 'A reminder to please complete your review', '<p>Dear {$recipientName}:</p><p>This email is an automated reminder from {$contextName} in regards to our request for your review of the submission, \"{$submissionTitle}.\"</p><p>We were expecting to have this review by {$reviewDueDate} and we would be pleased to receive it as soon as you are able to prepare it.</p><p>Please <a href=\"{$reviewAssignmentUrl}\">login to the journal</a> and follow the review steps to view the submission, upload review files, and submit your review comments.</p><p>If you need an extension of the deadline, please contact me. I look forward to hearing from you.</p><p>Thank you in advance and kind regards,</p>{$contextSignature}'),
(41, 'REVIEW_COMPLETE', 'de', 'Gutachten abgeschlossen', 'Gutachten abgeschlossen: {$reviewerName} empfiehlt {$reviewRecommendation} für #{$submissionId} {$authorsShort} - {$submissionTitle}', '<p>Sehr geehrte/r {$recipientName},</p><p>{$reviewerName} hat die folgende Überprüfung abgeschlossen:</p><p><a href=\"{$submissionUrl}\">#{$submissionId} {$authorsShort} — {$submissionTitle}</a><br /><b>Empfehlung:</b> {$reviewRecommendation}<br /><b>Gutachtenmethode:</b> {$reviewMethod}</p><p>Melden Sie sich an, um <a href=\"{$submissionUrl}\">alle Dateien und Kommentare</a> dieses Gutachters/dieser Gutachterin zu sehen.</p>'),
(42, 'REVIEW_COMPLETE', 'en', 'Review Completed', 'Review complete: {$reviewerName} recommends {$reviewRecommendation} for #{$submissionId} {$authorsShort} — \"{$submissionTitle}\"', '<p>Dear {$recipientName},</p><p>{$reviewerName} completed the following review:</p><p><a href=\"{$submissionUrl}\">#{$submissionId} {$authorsShort} — \"{$submissionTitle}\"</a><br /><b>Recommendation:</b> {$reviewRecommendation}<br /><b>Type:</b> {$reviewMethod}</p><p>Login to <a href=\"{$submissionUrl}\">view all files and comments</a> provided by this reviewer.</p>'),
(43, 'REVIEW_EDIT', 'de', 'Gutachten bearbeitet', 'Ihre Zuweisung für ein Gutachten für {$contextName} wurde geändert', '<p>Sehr geehrte/r {$recipientName},</p><p>Ein Redakteur/eine Redakteurin hat Änderungen an Ihrer Überprüfungsaufgabe für {$contextName} vorgenommen. Bitte überprüfen Sie die folgenden Details und lassen Sie uns wissen, wenn Sie Fragen haben.</p><p><a href=\"{$reviewAssignmentUrl}\">{$submissionTitle}</a><br /><b>Gutachtenmethode:</b> {$reviewMethod}<br /><b>Annehmen oder Ablehnen bis:</b> {$responseDueDate}<br /><b>Gutachteneinreichung bis:</b> {$reviewDueDate}</p><p>Sie können sich jederzeit anmelden, um <a href=\"{$reviewAssignmentUrl}\">diese Überprüfung abzuschließen</a>.</p>'),
(44, 'REVIEW_EDIT', 'en', 'Review Edited', 'Your review assignment has been changed for {$contextName}', '<p>Dear {$recipientName},</p><p>An editor has made changes to your review assignment for {$contextName}. Please review the details below and let us know if you have any questions.</p><p><a href=\"{$reviewAssignmentUrl}\">\"{$submissionTitle}\"</a><br /><b>Type:</b> {$reviewMethod}<br /><b>Accept or Decline By:</b> {$responseDueDate}<br /><b>Submit Review By:</b> {$reviewDueDate}</p><p>You can login to <a href=\"{$reviewAssignmentUrl}\">complete this review</a> at any time.</p>'),
(45, 'EDITOR_DECISION_ACCEPT', 'de', 'Einreichung akzeptiert', 'Ihre Einreichung wurde bei {$contextName} angenommen', '<p>Sehr geehrte/r {$recipientName},</p><p>ich freue mich, Ihnen mitteilen zu können, dass wir beschlossen haben, Ihre Einreichung ohne weitere Überarbeitung zu akzeptieren. Nach sorgfältiger Prüfung haben wir festgestellt, dass Ihr Beitrag {$submissionTitle} unsere Erwartungen erfüllt oder sogar übertrifft. Wir freuen uns, Ihren Beitrag in {$contextName} zu veröffentlichen, und danken Ihnen, dass Sie unsere Zeitschrift als Ort für Ihre Arbeit gewählt haben.</p><p>Ihr Beitrag wird nun in einer zukünftigen Ausgabe von {$contextName} erscheinen, und Sie können ihn gerne in Ihre Liste der Veröffentlichungen aufnehmen. Wir erkennen die harte Arbeit an, die in jeder erfolgreichen Einreichung steckt, und wir möchten Ihnen dazu gratulieren, dass Sie dieses Stadium erreicht haben.</p><p>Ihre Einreichung wird nun redaktionell bearbeitet und formatiert, um sie für die Veröffentlichung vorzubereiten.</p><p>Sie werden in Kürze weitere Anweisungen erhalten.</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über Ihr <a href=\"{$authorSubmissionUrl}\">Einreichungs-Dashboard</a>.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(46, 'EDITOR_DECISION_ACCEPT', 'en', 'Submission Accepted', 'Your submission has been accepted to {$contextName}', '<p>Dear {$recipientName},</p><p>I am pleased to inform you that we have decided to accept your submission without further revision. After careful review, we found your submission, {$submissionTitle}, to meet or exceed our expectations. We are excited to publish your piece in {$contextName} and we thank you for choosing our journal as a venue for your work.</p><p>Your submission is now forthcoming in a future issue of {$contextName} and you are welcome to include it in your list of publications. We recognize the hard work that goes into every successful submission and we want to congratulate you on reaching this stage.</p><p>Your submission will now undergo copy editing and formatting to prepare it for publication.</p><p>You will shortly receive further instructions.</p><p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p><p>Kind regards,</p>{$signature}'),
(47, 'EDITOR_DECISION_SEND_TO_EXTERNAL', 'de', 'Zur Begutachtung geschickt', 'Ihr Beitrag wurde zur Überprüfung gesendet', '<p>Sehr geehrte/r {$recipientName},</p><p>Ich freue mich, Ihnen mitzuteilen, dass ein Redakteur/eine Redakteurin Ihre Einreichung {$submissionTitle} begutachtet hat und beschlossen hat, sie zur Begutachtung zu schicken. Ein Redakteur/eine Redakteurin wird qualifizierte Gutachter/Gutachterinnen benennen, die ein Feedback zu Ihrer Einreichung geben werden.</p><p>{$reviewTypeDescription} Sie erhalten von uns eine Rückmeldung von den Gutachter/Gutachterinnen und Informationen über die nächsten Schritte.</p><p>Bitte beachten Sie, dass die Einsendung des Beitrags zur Begutachtung keine Garantie dafür ist, dass er veröffentlicht wird. Wir werden die Empfehlungen der Gutachter/innen berücksichtigen, bevor wir entscheiden, ob der Beitrag zur Veröffentlichung angenommen wird. Es kann sein, dass Sie aufgefordert werden, den Beitrag zu überarbeiten und auf die Kommentare der Gutachter/innen zu antworten, bevor eine endgültige Entscheidung getroffen wird.</p><p>Wenn Sie Fragen haben, wenden Sie sich bitte über das Dashboard Ihres Beitrags an mich.</p><p>{$signature}</p>'),
(48, 'EDITOR_DECISION_SEND_TO_EXTERNAL', 'en', 'Sent to Review', 'Your submission has been sent for review', '<p>Dear {$recipientName},</p><p>I am pleased to inform you that an editor has reviewed your submission, \"{$submissionTitle}\", and has decided to send it for peer review. An editor will identify qualified reviewers who will provide feedback on your submission.</p><p>{$reviewTypeDescription} You will hear from us with feedback from the reviewers and information about the next steps.</p><p>Please note that sending the submission to peer review does not guarantee that it will be published. We will consider the reviewers\' recommendations before deciding to accept the submission for publication. You may be asked to make revisions and respond to the reviewers\' comments before a final decision is made.</p><p>If you have any questions, please contact me from your submission dashboard.</p><p>{$signature}</p>'),
(49, 'EDITOR_DECISION_SEND_TO_PRODUCTION', 'de', 'In die Produktion geschickt', 'Nächste Schritte zur Veröffentlichung Ihres Beitrags', '<p>Sehr geehrte/r {$recipientName},</p><p>ich schreibe Ihnen von {$contextName}, um Ihnen mitzuteilen, dass die Bearbeitung Ihrer Einreichung {$submissionTitle} abgeschlossen ist. Ihre Einreichung geht nun in die Produktionsphase über, in der die endgültigen Druckfahnen für die Veröffentlichung vorbereitet werden. Wir werden uns mit Ihnen in Verbindung setzen, wenn wir weitere Unterstützung benötigen.</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=\"{$authorSubmissionUrl}\">Dashboard Ihrer Einreichung</a>.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(50, 'EDITOR_DECISION_SEND_TO_PRODUCTION', 'en', 'Sent to Production', 'Next steps for publishing your submission', '<p>Dear {$recipientName},</p><p>I am writing from {$contextName} to let you know that the editing of your submission, \"{$submissionTitle}\", is complete. Your submission will now advance to the production stage, where the final galleys will be prepared for publication. We will contact you if we need any further assistance.</p><p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p><p>Kind regards,</p>{$signature}'),
(51, 'EDITOR_DECISION_REVISIONS', 'de', 'Überarbeitungen angefordert', 'Ihr Beitrag wurde geprüft. Bitte reichen Sie Überarbeitungen ein', '<p>Sehr geehrte/r {$recipientName},</p><p>Ihre Einreichung {$submissionTitle} wurde begutachtet und wir möchten Sie auffordern, Überarbeitungen einzureichen, die auf die Kommentare der Gutachter/innen eingehen. Ein Redakteur/eine Redakteurin wird diese Überarbeitungen prüfen, und wenn sie die Bedenken hinreichend berücksichtigen, kann Ihr Beitrag zur Veröffentlichung angenommen werden.</p><p>Die Kommentare der Gutachter/innen sind am Ende dieser E-Mail enthalten. Bitte gehen Sie auf jeden Punkt in den Kommentaren der Reviewer ein und geben Sie an, welche Änderungen Sie vorgenommen haben. Wenn Sie einen der Kommentare für ungerechtfertigt oder unangemessen halten, erläutern Sie bitte Ihren Standpunkt.</p><p>Wenn Sie Ihre Überarbeitungen abgeschlossen haben, können Sie die überarbeiteten Dokumente zusammen mit Ihrer Antwort auf die Kommentare der Prüfer im <a href=„{$authorSubmissionUrl}“>Dashboard Ihrer Einreichung</a> hochladen. Wenn Sie abgemeldet waren, können Sie sich erneut mit dem Benutzernamen {$recipientUsername} anmelden.</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=„{$authorSubmissionUrl}“>Dashboard Ihrer Einreichung</a>.</p><p>Wir freuen uns auf Ihre überarbeitete Eingabe.</p><p>Mit freundlichen Grüßen,</p>{$signature}<hr><p>Die folgenden Kommentare wurden von den Gutachter/innen abgegeben.</p>{$allReviewerComments}'),
(52, 'EDITOR_DECISION_REVISIONS', 'en', 'Revisions Requested', 'Your submission has been reviewed and we encourage you to submit revisions', '<p>Dear {$recipientName},</p><p>Your submission \"{$submissionTitle}\" has been reviewed and we would like to encourage you to submit revisions that address the reviewers\' comments. An editor will review these revisions and if they address the concerns adequately, your submission may be accepted for publication.</p><p>The reviewers\' comments are included at the bottom of this email. Please respond to each point in the reviewers\' comments and identify what changes you have made. If you find any of the reviewer\'s comments to be unjustified or inappropriate, please explain your perspective.</p><p>When you have completed your revisions, you can upload revised documents along with your response to the reviewers\' comments at your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>. If you have been logged out, you can login again with the username {$recipientUsername}.</p><p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p><p>We look forward to receiving your revised submission.</p><p>Kind regards,</p>{$signature}<hr><p>The following comments were received from reviewers.</p>{$allReviewerComments}'),
(53, 'EDITOR_DECISION_RESUBMIT', 'de', 'Erneut für die Begutachtung vorlegen', 'Ihr Beitrag wurde geprüft - bitte überarbeiten Sie ihn und reichen Sie ihn erneut ein', '<p>Sehr geehrte/r {$recipientName},</p><p>Nach Durchsicht Ihrer Einreichung {$submissionTitle} haben die Gutachter/innen empfohlen, dass Ihre Einreichung in der vorliegenden Form nicht zur Veröffentlichung angenommen werden kann. Wir möchten Sie jedoch ermutigen, eine überarbeitete Version einzureichen, die auf die Kommentare der Gutachter/innen eingeht. Ihre überarbeitete Fassung wird von einem Redakteur/einer Redaktion geprüft und möglicherweise zu einer weiteren Begutachtungsrunde weitergeleitet.</p><p>Bitte beachten Sie, dass die erneute Einreichung Ihrer Arbeit keine Garantie dafür ist, dass sie angenommen wird.</p><p>Die Kommentare der Gutachter/innen sind am Ende dieser E-Mail enthalten. Bitte gehen Sie auf jeden Punkt ein und geben Sie an, welche Änderungen Sie vorgenommen haben. Wenn Sie einen der Kommentare für unangemessen halten, erläutern Sie bitte Ihren Standpunkt. Wenn Sie Fragen zu den Empfehlungen im Bericht haben, geben Sie diese bitte in Ihrer Antwort an.</p><p>Wenn Sie Ihre Überarbeitungen abgeschlossen haben, können Sie die überarbeiteten Dokumente zusammen mit Ihrer Antwort auf die Kommentare der Gutachter/innen im <a href=\"{$authorSubmissionUrl}\">Dashboard Ihrer Einreichung</a> hochladen.Wenn Sie abgemeldet waren, können Sie sich erneut mit dem Benutzernamen {$recipientUsername} anmelden.</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=„{$authorSubmissionUrl}“>Dashboard Ihrer Einreichung</a>.</p><p>Wir freuen uns auf Ihre überarbeitete Eingabe.</p><p>Mit freundlichen Grüßen,</p>{$signature}<hr><p>Die folgenden Kommentare wurden von den Gutachter/innen abgegeben.</p>{$allReviewerComments}'),
(54, 'EDITOR_DECISION_RESUBMIT', 'en', 'Resubmit for Review', 'Your submission has been reviewed - please revise and resubmit', '<p>Dear {$recipientName},</p><p>After reviewing your submission, \"{$submissionTitle}\", the reviewers have recommended that your submission cannot be accepted for publication in its current form. However, we would like to encourage you to submit a revised version that addresses the reviewers\' comments. Your revisions will be reviewed by an editor and may be sent out for another round of peer review.</p><p>Please note that resubmitting your work does not guarantee that it will be accepted.</p><p>The reviewers\' comments are included at the bottom of this email. Please respond to each point and identify what changes you have made. If you find any of the reviewer\'s comments inappropriate, please explain your perspective. If you have questions about the recommendations in your review, please include these in your response.</p><p>When you have completed your revisions, you can upload revised documents along with your response to the reviewers\' comments <a href=\"{$authorSubmissionUrl}\">at your submission dashboard</a>. If you have been logged out, you can login again with the username {$recipientUsername}.</p><p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p><p>We look forward to receiving your revised submission.</p><p>Kind regards,</p>{$signature}<hr><p>The following comments were received from reviewers.</p>{$allReviewerComments}'),
(55, 'EDITOR_DECISION_DECLINE', 'de', 'Einreichung abgelehnt', 'Entscheidung der Redaktion: Ihre Einreichung wurde abgelehnt', '<p>Sehr geehrte/ {$recipientName},</p><p>Wir freuen uns über Ihre Einreichung, können aber {$submissionTitle} auf Grund der Kommentare der Gutachter/innen nicht zur Veröffentlichung annehmen.</p><p>Die Kommentare der Gutachter/innen sind am Ende dieser E-Mail enthalten.</p><p>Vielen Dank für Ihre Einreichung bei {$contextName}. Obwohl es enttäuschend ist, wenn eine Einreichung abgelehnt wird, hoffe ich, dass Sie die Kommentare der Gutachter/innen als konstruktiv und hilfreich empfinden.</p><p>Es steht Ihnen nun frei, die Arbeit an anderer Stelle einzureichen, wenn Sie dies wünschen.</p><p>Mit freundlichen Grüßen,</p>{$signature}<hr><p>Die folgenden Kommentare wurden von den Gutachter/innen abgegeben.</p>{$allReviewerComments}'),
(56, 'EDITOR_DECISION_DECLINE', 'en', 'Submission Declined', 'Your submission has been declined', '<p>Dear {$recipientName},</p><p>While we appreciate receiving your submission, we are unable to accept \"{$submissionTitle}\" for publication on the basis of the comments from reviewers.</p><p>The reviewers\' comments are included at the bottom of this email.</p><p>Thank you for submitting to {$contextName}. Although it is disappointing to have a submission declined, I hope you find the reviewers\' comments to be constructive and helpful.</p><p>You are now free to submit the work elsewhere if you choose to do so.</p><p>Kind regards,</p>{$signature}<hr><p>The following comments were received from reviewers.</p>{$allReviewerComments}'),
(57, 'EDITOR_DECISION_INITIAL_DECLINE', 'de', 'Einreichung abgelehnt (Vor dem Gutachten)', 'Entscheidung der Redaktion: Ihre Einreichung wurde abgelehnt', '<p>Sehr geehrte/r {$recipientName},</p><p>es tut mir leid, Ihnen mitzuteilen, dass die Redaktion nach Durchsicht Ihrer Einreichung {$submissionTitle} festgestellt hat, dass sie unsere Anforderungen für eine Veröffentlichung in {$contextName} nicht erfüllt.</p><p>Ich wünsche Ihnen viel Erfolg, wenn Sie erwägen, Ihre Arbeit anderswo einzureichen.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(58, 'EDITOR_DECISION_INITIAL_DECLINE', 'en', 'Submission Declined (Pre-Review)', 'Your submission has been declined', '<p>Dear {$recipientName},</p><p>I’m sorry to inform you that, after reviewing your submission, \"{$submissionTitle}\", the editor has found that it does not meet our requirements for publication in {$contextName}.</p><p>I wish you success if you consider submitting your work elsewhere.</p><p>Kind regards,</p>{$signature}'),
(59, 'EDITOR_RECOMMENDATION', 'de', 'Empfehlung gegeben', 'Empfehlung der Redaktion', '<p>Sehr geehrte/r {$recipientName},</p><p>Nach Abwägung des Feedbacks der Gutachter/innen möchte ich folgende Empfehlung für die Einreichung {$submissionTitle} aussprechen .</p><p>Meine Empfehlung lautet: {$recommendation}.</p><p>Bitte besuchen Sie den <a href=\"{$submissionUrl}\">Redaktionsworkflow</a> des Beitrags, um dieser Empfehlung nachzukommen.</p><p>Bitte zögern Sie nicht, mich bei Fragen zu kontaktieren.</p><p>Mit freundlichen Grüßen,</p><p>{$senderName}</p>'),
(60, 'EDITOR_RECOMMENDATION', 'en', 'Recommendation Made', 'Editor Recommendation', '<p>Dear {$recipientName},</p><p>After considering the reviewers\' feedback, I would like to make the following recommendation regarding the submission \"{$submissionTitle}\".</p><p>My recommendation is: {$recommendation}.</p><p>Please visit the submission\'s <a href=\"{$submissionUrl}\">editorial workflow</a> to act on this recommendation.</p><p>Please feel free to contact me with any questions.</p><p>Kind regards,</p><p>{$senderName}</p>'),
(61, 'EDITOR_DECISION_NOTIFY_OTHER_AUTHORS', 'de', 'Andere Autor/innen benachrichtigen', 'Ein Update zu Ihrer Einreichung', '<p>Die folgende E-Mail wurde an {$submittingAuthorName} von {$contextName} bezüglich {$submissionTitle} gesendet.</p>\n<p>Sie erhalten eine Kopie dieser Benachrichtigung, da Sie als Autor/in der Einreichung identifiziert sind. Alle Anweisungen in der folgenden Nachricht sind für den einreichenden Autor/die einreichende Autorin {$submittingAuthorName} bestimmt, und von Ihnen wird zu diesem Zeitpunkt keine Aktion verlangt.</p>\n\n{$messageToSubmittingAuthor}'),
(62, 'EDITOR_DECISION_NOTIFY_OTHER_AUTHORS', 'en', 'Notify Other Authors', 'An update regarding your submission', '<p>The following email was sent to {$submittingAuthorName} from {$contextName} regarding \"{$submissionTitle}\".</p>\n<p>You are receiving a copy of this notification because you are identified as an author of the submission. Any instructions in the message below are intended for the submitting author, {$submittingAuthorName}, and no action is required of you at this time.</p>\n\n{$messageToSubmittingAuthor}');
INSERT INTO `email_templates_default_data` (`email_templates_default_data_id`, `email_key`, `locale`, `name`, `subject`, `body`) VALUES
(63, 'EDITOR_DECISION_NOTIFY_REVIEWERS', 'de', 'Gutachter/innen über die Entscheidung benachrichtigen', 'Eingangsbestätigung für Ihr Gutachten', '<p>Sehr geehrte/r {$recipientName},</p>\n<p>Vielen Dank, dass Sie den Beitrag {$submissionTitle} für {$contextName} begutachtet haben. Wir wissen Ihre Zeit und Ihr Fachwissen zu schätzen, mit denen Sie zur Qualität der von uns veröffentlichten Arbeiten beitragen. Wir haben Ihre Kommentare mit den Autoren geteilt, zusammen mit den Kommentaren unserer anderen Gutachter und der Entscheidung der Redaktion.</p>\n<p>Auf der Grundlage des erhaltenen Feedbacks haben wir die Autoren über Folgendes informiert:</p>\n<p>{$decisionDescription}</p>\n<p>Ihre Empfehlung wurde zusammen mit den Empfehlungen der anderen Gutachter/innen geprüft, bevor eine Entscheidung getroffen wurde. Gelegentlich kann die Entscheidung des Herausgebers von der Empfehlung eines oder mehrerer Gutachter/innen abweichen. Die Redaktion wägt viele Faktoren ab und nimmt diese Entscheidungen nicht auf die leichte Schulter. Wir sind dankbar für das Fachwissen und die Vorschläge unserer Gutachter/innen.</p>\n<p>Es war uns eine Freude, mit Ihnen als Gutachter/in für {$contextName} zusammenzuarbeiten, und wir hoffen, dass wir auch in Zukunft die Gelegenheit haben werden, mit Ihnen zusammenzuarbeiten.</p>\n<p>Mit freundlichen Grüßen,</p>\n<p>{$Unterschrift}</p>'),
(64, 'EDITOR_DECISION_NOTIFY_REVIEWERS', 'en', 'Notify Reviewers of Decision', 'Thank you for your review', '<p>Dear {$recipientName},</p>\n<p>Thank you for completing your review of the submission, \"{$submissionTitle}\", for {$contextName}. We appreciate your time and expertise in contributing to the quality of the work that we publish. We have shared your comments with the authors, along with our other reviewers\' comments and the editor\'s decision.</p>\n<p>Based on the feedback we received, we have notified the authors of the following:</p>\n<p>{$decisionDescription}</p>\n<p>Your recommendation was considered alongside the recommendations of other reviewers before coming to a decision. Occasionally the editor\'s decision may differ from the recommendation made by one or more reviewers. The editor considers many factors, and does not take these decisions lightly. We are grateful for our reviewers\' expertise and suggestions.</p>\n<p>It has been a pleasure to work with you as a reviewer for {$contextName}, and we hope to have the opportunity to work with you again in the future.</p>\n<p>Kind regards,</p>\n<p>{$signature}</p>'),
(65, 'EDITOR_DECISION_NEW_ROUND', 'de', 'Neue Gutachtenrunde gestartet', 'Ihr Beitrag wurde für eine weitere Begutachtungsrunde weitergeleitet', '<p>Sehr geehrte/r {$recipientName},</p>\n<p>Ihre überarbeitete Einreichung {$submissionTitle} wurde zu einer neuen Runde der Begutachtung geschickt. \nSie werden von uns eine Rückmeldung von den Gutachter/innen und Informationen über die nächsten Schritte erhalten.</p>\n<p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=\"{$authorSubmissionUrl}\">Dashboard Ihrer Einreichung</a>.</p>\n<p>Mit freundlichen Grüßen,</p>\n<p>{$signature}</p>\n'),
(66, 'EDITOR_DECISION_NEW_ROUND', 'en', 'New Review Round Initiated', 'Your submission has been sent for another round of review', '<p>Dear {$recipientName},</p>\n<p>Your revised submission, \"{$submissionTitle}\", has been sent for a new round of peer review. \nYou will hear from us with feedback from the reviewers and information about the next steps.</p>\n<p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p>\n<p>Kind regards,</p>\n<p>{$signature}</p>\n'),
(67, 'EDITOR_DECISION_REVERT_DECLINE', 'de', 'Abgelehnte Einreichung wieder aufnehmen', 'Wir haben die Entscheidung, Ihre Einreichung abzulehnen, rückgängig gemacht', '<p>Sehr geehrte/r {$recipientName},</p>\n<p>Die Entscheidung, Ihre Einreichung {$submissionTitle} abzulehnen, wurde rückgängig gemacht. Ein Redakteur/eine Redakteurin wird die Überprüfungsrunde abschließen und Sie werden benachrichtigt, wenn eine Entscheidung getroffen ist.</p>\n<p>Gelegentlich wird die Entscheidung, eine Einreichung abzulehnen, versehentlich in unserem System gespeichert und muss dann rückgängig gemacht werden. Ich möchte mich für die Verwirrung entschuldigen, die dies verursacht haben könnte.</p>\n<p>Wir werden uns mit Ihnen in Verbindung setzen, wenn wir weitere Unterstützung benötigen.</p>\n<p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=\"{$authorSubmissionUrl}\">Dashboard Ihrer Einreichung</a>.</p>\n<p>Mit freundlichen Grüßen,</p>\n<p>{$signature}</p>\n'),
(68, 'EDITOR_DECISION_REVERT_DECLINE', 'en', 'Reinstate Declined Submission', 'We have reversed the decision to decline your submission', '<p>Dear {$recipientName},</p>\n<p>The decision to decline your submission, \"{$submissionTitle}\", has been reversed. \nAn editor will complete the round of review and you will be notified when a \ndecision is made.</p>\n<p>Occasionally, a decision to decline a submission will be recorded accidentally in \nour system and must be reverted. I apologize for any confusion this may have caused.</p>\n<p>We will contact you if we need any further assistance.</p>\n<p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p>\n<p>Kind regards,</p>\n<p>{$signature}</p>\n'),
(69, 'EDITOR_DECISION_REVERT_INITIAL_DECLINE', 'de', 'Ohne Gutachten abgelehnte Einreichung wieder aufnehmen', 'Wir haben die Entscheidung, Ihre Einreichung abzulehnen, rückgängig gemacht', '<p>Sehr geehrte/r {$recipientName},</p><p>Die Entscheidung, Ihre Einreichung {$submissionTitle} abzulehnen, wurde rückgängig gemacht. Ein Redakteur/eine Redakteurin wird sich Ihre Einreichung genauer ansehen, bevor er entscheidet, ob er sie ablehnt oder zur Überprüfung zu schickt.</p><p>Gelegentlich wird die Entscheidung, eine Einreichung abzulehnen, versehentlich in unserem System gespeichert und muss dann rückgängig gemacht werden. Ich möchte mich für die Verwirrung entschuldigen, die dies verursacht haben könnte.</p><p>Wir werden uns mit Ihnen in Verbindung setzen, wenn wir weitere Unterstützung benötigen.</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=\"{$authorSubmissionUrl}\">Dashboard Ihrer Einreichung</a>.</p><p>Mit freundlichen Grüßen,</p><p>{$signature}</p>\n'),
(70, 'EDITOR_DECISION_REVERT_INITIAL_DECLINE', 'en', 'Reinstate Submission Declined Without Review', 'We have reversed the decision to decline your submission', '<p>Dear {$recipientName},</p>\n<p>The decision to decline your submission, \"{$submissionTitle}\", has been reversed. \nAn editor will look further at your submission before deciding whether to decline \nthe submission or send it for review.</p>\n<p>Occasionally, a decision to decline a submission will be recorded accidentally in \nour system and must be reverted. I apologize for any confusion this may have caused.</p>\n<p>We will contact you if we need any further assistance.</p>\n<p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p>\n<p>Kind regards,</p>\n<p>{$signature}</p>\n'),
(71, 'EDITOR_DECISION_SKIP_REVIEW', 'de', 'Einreichung angenommen (ohne Begutachtung)', 'Ihre Einreichung wurde in das Lektorat geschickt', '<p>Sehr geehrte/r {$recipientName},</p>\n<p>Ich freue mich, Ihnen mitteilen zu können, dass wir beschlossen haben, Ihre Einreichung ohne Peer Review anzunehmen. Wir sind der Meinung, dass Ihre Einreichung {$submissionTitle} unseren Erwartungen entspricht, und wir verlangen nicht, dass Arbeiten dieser Art einem Peer-Review unterzogen werden. Wir freuen uns, Ihren Beitrag in {$contextName} zu veröffentlichen und danken Ihnen, dass Sie unsere Zeitschrift für Ihre Arbeit gewählt haben.</p>\nIhr Beitrag wird nun in einer zukünftigen Ausgabe von {$contextName} veröffentlicht und Sie können ihn gerne in Ihre Publikationsliste aufnehmen. Wir wissen um die harte Arbeit, die hinter jeder erfolgreichen Einreichung steckt, und möchten Ihnen zu Ihren Bemühungen gratulieren.</p>>\n<p>Ihre Einreichung wird nun redaktionell bearbeitet und formatiert, um sie für die Veröffentlichung vorzubereiten. </p>\n<p>Sie werden in Kürze weitere Anweisungen erhalten.</p>\n<p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über Ihr <a href=\"{$authorSubmissionUrl}\">Einreichungs-Dashboard</a>.</p>\n<p>Mit freundlichen Grüßen,</p>\n<p>{$signature}</p>\n'),
(72, 'EDITOR_DECISION_SKIP_REVIEW', 'en', 'Submission Accepted (Without Review)', 'Your submission has been sent for copyediting', '<p>Dear {$recipientName},</p>\n<p>I am pleased to inform you that we have decided to accept your submission without peer review. We found your submission, {$submissionTitle}, to meet our expectations, and we do not require that work of this type undergo peer review. We are excited to publish your piece in {$contextName} and we thank you for choosing our journal as a venue for your work.</p>\nYour submission is now forthcoming in a future issue of {$contextName} and you are welcome to include it in your list of publications. We recognize the hard work that goes into every successful submission and we want to congratulate you on your efforts.</p>\n<p>Your submission will now undergo copy editing and formatting to prepare it for publication. </p>\n<p>You will shortly receive further instructions.</p>\n<p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p>\n<p>Kind regards,</p>\n<p>{$signature}</p>\n'),
(73, 'EDITOR_DECISION_BACK_FROM_PRODUCTION', 'de', 'Einreichung wurde ins Lektorat zurückgeschickt', 'Ihr Beitrag wurde an das Lektorat zurückgeschickt', '<p>Sehr geehrte/r {$recipientName},</p><p>Ihre Einsendung {$submissionTitle} wurde an das Lektorat zurückgeschickt, wo sie weiter bearbeitet und formatiert wird, um sie zur Veröffentlichung vorzubereiten.</p><p>Es kann vorkommen, dass eine Einsendung in die Produktionsphase geht, bevor die endgültigen Druckfahnen für die Veröffentlichung vorbereitet sind. Ihre Einsendung ist noch nicht abgeschlossen. Für etwaige Unklarheiten bitte ich um Entschuldigung.</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=\"{$authorSubmissionUrl}\">Dashboard Ihrer Einreichung</a>.</p><p>Wir werden uns mit Ihnen in Verbindung setzen, wenn wir weitere Unterstützung benötigen.</p><p>Mit freundlichen Grüßen,</p><p>{$signature}</p>'),
(74, 'EDITOR_DECISION_BACK_FROM_PRODUCTION', 'en', 'Submission Sent Back to Copyediting', 'Your submission has been sent back to copyediting', '<p>Dear {$recipientName},</p><p>Your submission, \"{$submissionTitle}\", has been sent back to the copyediting stage, where it will undergo further copyediting and formatting to prepare it for publication.</p><p>Occasionally, a submission is sent to the production stage before it is ready for the final galleys to be prepared for publication. Your submission is still forthcoming. I apologize for any confusion.</p><p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p><p>We will contact you if we need any further assistance.</p><p>Kind regards,</p><p>{$signature}</p>'),
(75, 'EDITOR_DECISION_BACK_FROM_COPYEDITING', 'de', 'Einreichung vom Lektorat aus zurückgeschickt', 'Ihr Beitrag wurde zur Überprüfung zurückgeschickt', '<p>Sehr geehrte/r {$recipientName},</p><p>ihr Beitrag {$submissionTitle} wurde zur Überprüfung zurückgeschickt. Er wird einer weiteren Überprüfung unterzogen, bevor er zur Veröffentlichung angenommen werden kann.</p><p>Gelegentlich wird eine Entscheidung über die Annahme einer Einreichung versehentlich in unserem System gespeichert und wir müssen sie zur Überprüfung zurückschicken. Ich bitte um Entschuldigung für jegliche Verwirrung, die dies verursacht hat. Wir werden uns bemühen, alle weiteren Überprüfungen schnell abzuschließen, damit Sie so schnell wie möglich eine endgültige Entscheidung erhalten.</p><p>Wir werden uns mit Ihnen in Verbindung setzen, wenn wir weitere Unterstützung benötigen.</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=\"{$authorSubmissionUrl}\">Dashboard Ihrer Einreichung</a>.</p><p>Mit freundlichen Grüßen,</p><p>{$signature}</p>'),
(76, 'EDITOR_DECISION_BACK_FROM_COPYEDITING', 'en', 'Submission Sent Back from Copyediting', 'Your submission has been sent back to review', '<p>Dear {$recipientName},</p><p>Your submission, \"{$submissionTitle}\", has been sent back to the review stage. It will undergo further review before it can be accepted for publication.</p><p>Occasionally, a decision to accept a submission will be recorded accidentally in our system and we must send it back to review. I apologize for any confusion this has caused. We will work to complete any further review quickly so that you have a final decision as soon as possible.</p><p>We will contact you if we need any further assistance.</p><p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p><p>Kind regards,</p><p>{$signature}</p>'),
(77, 'EDITOR_DECISION_CANCEL_REVIEW_ROUND', 'de', 'Gutachtenrunde abgebrochen', 'Eine Überprüfungsrunde für Ihren Beitrag wurde abgesagt', '<p>Sehr geehrte/r {$recipientName},</p><p>wir haben kürzlich eine neue Überprüfungsrunde für Ihre Einreichung {$submissionTitle} eröffnet. Wir schließen diese Überprüfungsrunde jetzt ab.</p><p>Es kann vorkommen, dass eine Entscheidung zur Eröffnung einer Überprüfungsrunde versehentlich in unserem System gespeichert wird und wir diese Überprüfungsrunde abbrechen müssen. Ich bitte um Entschuldigung für jegliche Verwirrung, die dies verursacht haben könnte.</p><p>Wir werden uns mit Ihnen in Verbindung setzen, wenn wir weitere Unterstützung benötigen.</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte über das <a href=\"{$authorSubmissionUrl}\">Dashboard Ihrer Einreichung</a>.</p><p>Mit freundlichen Grüßen,</p><p>{$signature}</p>'),
(78, 'EDITOR_DECISION_CANCEL_REVIEW_ROUND', 'en', 'Review Round Cancelled', 'A review round for your submission has been cancelled', '<p>Dear {$recipientName},</p><p>We recently opened a new review round for your submission, \"{$submissionTitle}\". We are closing this review round now.</p><p>Occasionally, a decision to open a round of review will be recorded accidentally in our system and we must cancel this review round. I apologize for any confusion this may have caused.</p><p>We will contact you if we need any further assistance.</p><p>If you have any questions, please contact me from your <a href=\"{$authorSubmissionUrl}\">submission dashboard</a>.</p><p>Kind regards,</p><p>{$signature}</p>'),
(79, 'SUBSCRIPTION_NOTIFY', 'de', 'Abonnementbenachrichtigung', 'Benachrichtigung zu einem Abonnement', 'Sehr geehrte/r {$recipientName}<br />\n<br />\nSie sind jetzt als Abonnent/in in unserem Online-Zeitschriftenverwaltungssystem für {$contextName} mit dem folgenden Abonnement registriert:<br />\n<br />\n{$subscriptionType}<br />\n<br />\nUm auf Inhalte zuzugreifen, die nur für Abonnent/innen verfügbar sind, melden Sie sich einfach mit Ihrem Benutzernamen \"{$recipientUsername}\" am System an.<br />\n<br />\nSobald Sie sich im System angemeldet haben, können Sie Ihre Profildaten und Ihr Passwort jederzeit ändern.<br />\n<br />\nWenn Sie ein institutionelles Abonnement haben, brauchen sich die Benutzer/innen Ihrer Einrichtung nicht anzumelden, da Anfragen nach Abonnementinhalten automatisch vom System authentifiziert werden.<br />\n<br />\nWenn Sie Fragen haben, können Sie sich gerne an mich wenden.<br />\n<br />\n{$subscriptionSignature}'),
(80, 'SUBSCRIPTION_NOTIFY', 'en', 'Subscription Notify', 'Subscription Notification', '{$recipientName}:<br />\n<br />\nYou have now been registered as a subscriber in our online journal management system for {$contextName}, with the following subscription:<br />\n<br />\n{$subscriptionType}<br />\n<br />\nTo access content that is available only to subscribers, simply log in to the system with your username, &quot;{$recipientUsername}&quot;.<br />\n<br />\nOnce you have logged in to the system you can change your profile details and password at any point.<br />\n<br />\nPlease note that if you have an institutional subscription, there is no need for users at your institution to log in, since requests for subscription content will be automatically authenticated by the system.<br />\n<br />\nIf you have any questions, please feel free to contact me.<br />\n<br />\n{$subscriptionSignature}'),
(81, 'OPEN_ACCESS_NOTIFY', 'de', 'Open Access Benachrichtigung', 'Frei zugänglich: {$issueIdentification} der Zeitschrift {$contextName} ist jetzt Open Access', '<p>Sehr geehrte/r {$recipientName},</p><p>wir freuen uns, Ihnen mitteilen zu können, dass <a href=\"{$issueUrl}\">{$issueIdentification}</a> der Zeitschrift {$contextName} jetzt im Open Access verfügbar ist. Ein Abonnement ist nicht mehr erforderlich, um diese Ausgabe zu lesen.</p><p>Wir danken Ihnen für Ihr anhaltendes Interesse an unserer Arbeit.</p>{$contextSignature}'),
(82, 'OPEN_ACCESS_NOTIFY', 'en', 'Open Access Notify', 'Free to read: {$issueIdentification} of {$contextName} is now open access', '<p>Dear {$recipientName},</p><p>We are pleased to inform you that <a href=\"{$issueUrl}\">{$issueIdentification}</a> of {$contextName} is now available under open access.  A subscription is no longer required to read this issue.</p><p>Thank you for your continuing interest in our work.</p>{$contextSignature}'),
(83, 'SUBSCRIPTION_BEFORE_EXPIRY', 'de', 'Abonnement läuft bald aus', 'Benachrichtigung über das Ablaufen eines Abonnements', 'Sehr geehrte/r {$recipientName}<br />\n<br />\nIhr Abonnement für {$contextName} läuft in Kürze ab.<br />\n<br />\n{$subscriptionType}<br />\nVerfallsdatum: {$expiryDate}<br />\n<br />\nUm sicherzustellen, dass Sie weiterhin Zugang zu dieser Zeitschrift haben, gehen Sie bitte auf die Website der Zeitschrift und verlängern Sie Ihr Abonnement. Sie können sich mit Ihrem Benutzernamen \"{$recipientUsername}\" in das System einloggen.<br />\n<br />\nWenn Sie Fragen haben, können Sie mich gerne kontaktieren.<br />\n<br />\n{$subscriptionSignature}'),
(84, 'SUBSCRIPTION_BEFORE_EXPIRY', 'en', 'Subscription Expires Soon', 'Notice of Subscription Expiry', '{$recipientName}:<br />\n<br />\nYour {$contextName} subscription is about to expire.<br />\n<br />\n{$subscriptionType}<br />\nExpiry date: {$expiryDate}<br />\n<br />\nTo ensure the continuity of your access to this journal, please go to the journal website and renew your subscription. You are able to log in to the system with your username, &quot;{$recipientUsername}&quot;.<br />\n<br />\nIf you have any questions, please feel free to contact me.<br />\n<br />\n{$subscriptionSignature}'),
(85, 'SUBSCRIPTION_AFTER_EXPIRY', 'de', 'Abonnement abgelaufen', 'Abonnement abgelaufen', 'Sehr geehrte/r {$recipientName}<br />\n<br />\nIhr Abonnement für {$contextName} ist abgelaufen.<br />\n<br />\n{$subscriptionType}<br />\nVerfallsdatum: {$expiryDate}<br />\n<br />\nUm Ihr Abonnement zu erneuern, gehen Sie bitte auf die Website der Zeitschrift. Sie können sich mit Ihrem Benutzernamen \"{$recipientUsername}\" in das System einloggen.<br />\n<br />\nWenn Sie Fragen haben, können Sie mich gerne kontaktieren.<br />\n<br />\n{$subscriptionSignature}'),
(86, 'SUBSCRIPTION_AFTER_EXPIRY', 'en', 'Subscription Expired', 'Subscription Expired', '{$recipientName}:<br />\n<br />\nYour {$contextName} subscription has expired.<br />\n<br />\n{$subscriptionType}<br />\nExpiry date: {$expiryDate}<br />\n<br />\nTo renew your subscription, please go to the journal website. You are able to log in to the system with your username, &quot;{$recipientUsername}&quot;.<br />\n<br />\nIf you have any questions, please feel free to contact me.<br />\n<br />\n{$subscriptionSignature}'),
(87, 'SUBSCRIPTION_AFTER_EXPIRY_LAST', 'de', 'Zuletzt abgelaufenes Abbonement', 'Abonnement abgelaufen - Letzte Erinnerung', 'Sehr geehrte/r {{$recipientName}<br />\n<br />\nIhr Abonnement für {$contextName} ist abgelaufen.<br />\nBitte beachten Sie, dass dies die letzte Erinnerung ist, die Sie per E-Mail erhalten werden.<br />\n<br />\n{$subscriptionType}<br />\nVerfallsdatum: {$expiryDate}<br />\n<br />\nUm Ihr Abonnement zu verlängern, gehen Sie bitte auf die Website der Zeitschrift. Sie können sich mit Ihrem Benutzernamen \"{$recipientUsername}\" in das System einloggen.<br />\n<br />\nWenn Sie Fragen haben, können Sie mich gerne kontaktieren.<br />\n<br />\n{$subscriptionSignature}'),
(88, 'SUBSCRIPTION_AFTER_EXPIRY_LAST', 'en', 'Subscription Expired Last', 'Subscription Expired - Final Reminder', '{$recipientName}:<br />\n<br />\nYour {$contextName} subscription has expired.<br />\nPlease note that this is the final reminder that will be emailed to you.<br />\n<br />\n{$subscriptionType}<br />\nExpiry date: {$expiryDate}<br />\n<br />\nTo renew your subscription, please go to the journal website. You are able to log in to the system with your username, &quot;{$recipientUsername}&quot;.<br />\n<br />\nIf you have any questions, please feel free to contact me.<br />\n<br />\n{$subscriptionSignature}'),
(89, 'SUBSCRIPTION_PURCHASE_INDL', 'de', 'Ein individuelles Abonnement kaufen', 'Abonnementkauf: Individuell', 'Ein individuelles Abonnement wurde online für {$contextName} mit den folgenden Details erworben.<br />\n<br />\nAbonnementtyp:<br />\n{$subscriptionType}<br />\n<br />\nBenutzer:<br />\n{$subscriberDetails}<br />\n<br />\nMitgliedschaftsinformationen (falls vorhanden):<br />\n{$membership}<br />\n<br />\nUm dieses Abonnement anzuzeigen oder zu bearbeiten, verwenden Sie bitte die folgende URL.<br />\n<br />\nAbonnement-URL: {$subscriptionUrl}<br />\n'),
(90, 'SUBSCRIPTION_PURCHASE_INDL', 'en', 'Purchase Individual Subscription', 'Subscription Purchase: Individual', 'An individual subscription has been purchased online for {$contextName} with the following details.<br />\n<br />\nSubscription Type:<br />\n{$subscriptionType}<br />\n<br />\nUser:<br />\n{$subscriberDetails}<br />\n<br />\nMembership Information (if provided):<br />\n{$membership}<br />\n<br />\nTo view or edit this subscription, please use the following URL.<br />\n<br />\nSubscription URL: {$subscriptionUrl}<br />\n'),
(91, 'SUBSCRIPTION_PURCHASE_INSTL', 'de', 'Ein institutionelles Abonnement kaufen', 'Abonnementkauf: Institutionell', 'Ein institutionelles Abonnement wurde online für {$contextName} mit den folgenden Details erworben. Um dieses Abonnement zu aktivieren, verwenden Sie bitte die bereitgestellte Abonnement-URL und setzen Sie den Abonnementstatus auf \'Aktiv\'.<br />\n<br />\nAbonnementtyp:<br />\n{$subscriptionType}<br />\n<br />\nInstitution:<br />\n{$institutionName}<br />\n{$institutionMailingAddress}<br />\n<br />\nDomain (falls vorhanden):<br />\n{$domain}<br />\n<br />\nIP-Bereiche (falls angegeben):<br />\n{$ipRanges}<br />\n<br />\nKontaktperson:<br />\n{$subscriberDetails}<br />\n<br />\nMitgliedschaftsinformationen (falls vorhanden):<br />\n{$membership}<br />\n<br />\nUm dieses Abonnement anzuzeigen oder zu bearbeiten, verwenden Sie bitte die folgende URL.<br />\n<br />\nAbonnement-URL: {$subscriptionUrl}<br />\n'),
(92, 'SUBSCRIPTION_PURCHASE_INSTL', 'en', 'Purchase Institutional Subscription', 'Subscription Purchase: Institutional', 'An institutional subscription has been purchased online for {$contextName} with the following details. To activate this subscription, please use the provided Subscription URL and set the subscription status to \'Active\'.<br />\n<br />\nSubscription Type:<br />\n{$subscriptionType}<br />\n<br />\nInstitution:<br />\n{$institutionName}<br />\n{$institutionMailingAddress}<br />\n<br />\nDomain (if provided):<br />\n{$domain}<br />\n<br />\nIP Ranges (if provided):<br />\n{$ipRanges}<br />\n<br />\nContact Person:<br />\n{$subscriberDetails}<br />\n<br />\nMembership Information (if provided):<br />\n{$membership}<br />\n<br />\nTo view or edit this subscription, please use the following URL.<br />\n<br />\nSubscription URL: {$subscriptionUrl}<br />\n'),
(93, 'SUBSCRIPTION_RENEW_INDL', 'de', 'Individuelles Abonnement erneuern', 'Abonnementerneuerung: Individuell', 'Ein individuelles Abonnement wurde online für {$contextName} mit den folgenden Details verlängert.<br />\n<br />\nAbonnementtyp:<br />\n{$subscriptionType}<br />\n<br />\nBenutzer:<br />\n{$subscriberDetails}<br />\n<br />\nMitgliedschaftsinformationen (falls vorhanden):<br />\n{$membership}<br />\n<br />\nUm dieses Abonnement anzuzeigen oder zu bearbeiten, verwenden Sie bitte die folgende URL.<br />\n<br />\nAbonnement-URL: {$subscriptionUrl}<br />\n'),
(94, 'SUBSCRIPTION_RENEW_INDL', 'en', 'Renew Individual Subscription', 'Subscription Renewal: Individual', 'An individual subscription has been renewed online for {$contextName} with the following details.<br />\n<br />\nSubscription Type:<br />\n{$subscriptionType}<br />\n<br />\nUser:<br />\n{$subscriberDetails}<br />\n<br />\nMembership Information (if provided):<br />\n{$membership}<br />\n<br />\nTo view or edit this subscription, please use the following URL.<br />\n<br />\nSubscription URL: {$subscriptionUrl}<br />\n'),
(95, 'SUBSCRIPTION_RENEW_INSTL', 'de', 'Institutionelles Abonnement erneuern', 'Abonnementerneuerung: Institutionell', 'Ein institutionelles Abonnement wurde online für {$contextName} mit den folgenden Angaben verlängert.<br />\n<br />\nAbonnementtyp:<br />\n{$subscriptionType}<br />\n<br />\nInstitution:<br />\n{$institutionName}<br />\n{$institutionMailingAddress}<br />\n<br />\nDomain (falls vorhanden):<br />\n{$domain}<br />\n<br />\nIP-Bereiche (falls angegeben):<br />\n{$ipRanges}<br />\n<br />\nKontaktperson:<br />\n{$subscriberDetails}<br />\n<br />\nMitgliedschaftsinformationen (falls vorhanden):<br />\n{$membership}<br />\n<br />\nUm dieses Abonnement anzuzeigen oder zu bearbeiten, verwenden Sie bitte die folgende URL.<br />\n<br />\nAbonnement-URL: {$subscriptionUrl}<br />\n'),
(96, 'SUBSCRIPTION_RENEW_INSTL', 'en', 'Renew Institutional Subscription', 'Subscription Renewal: Institutional', 'An institutional subscription has been renewed online for {$contextName} with the following details.<br />\n<br />\nSubscription Type:<br />\n{$subscriptionType}<br />\n<br />\nInstitution:<br />\n{$institutionName}<br />\n{$institutionMailingAddress}<br />\n<br />\nDomain (if provided):<br />\n{$domain}<br />\n<br />\nIP Ranges (if provided):<br />\n{$ipRanges}<br />\n<br />\nContact Person:<br />\n{$subscriberDetails}<br />\n<br />\nMembership Information (if provided):<br />\n{$membership}<br />\n<br />\nTo view or edit this subscription, please use the following URL.<br />\n<br />\nSubscription URL: {$subscriptionUrl}<br />\n'),
(97, 'REVISED_VERSION_NOTIFY', 'de', 'Benachrichtigung über eine überarbeitete Version', 'Überarbeitete Version hochgeladen', '<p>Liebe/r {$recipientName},</p><p>der Autor/die Autorin hat Überarbeitungen für die Einreichung <b>{$authorsShort} - {$submissionTitle}</b> hochgeladen. <p>Als zugewiesene/r Redakteur/in bitten wir Sie, sich einzuloggen und <a href=\"{$submissionUrl}\">die Überarbeitungen</a> anzusehen und eine Entscheidung zu treffen, ob Sie die Einreichung annehmen, ablehnen oder zur weiteren Überprüfung weiterleiten möchten.</p><br><br>-<br>Dies ist eine automatische Nachricht von <a href=\"{$contextUrl}\">{$contextName}</a>.'),
(98, 'REVISED_VERSION_NOTIFY', 'en', 'Revised Version Notification', 'Revised Version Uploaded', '<p>Dear {$recipientName},</p><p>The author has uploaded revisions for the submission, <b>{$authorsShort} — {$submissionTitle}</b>. <p>As an assigned editor, we ask that you login and <a href=\"{$submissionUrl}\">view the revisions</a> and make a decision to accept, decline or send the submission for further review.</p><br><br>—<br>This is an automated message from <a href=\"{$contextUrl}\">{$contextName}</a>.'),
(99, 'STATISTICS_REPORT_NOTIFICATION', 'de', 'Benachrichtigung über einen Statistikbericht', 'Redaktionelle Aktivitäten für {$month}, {$year}', '\nSehr geehrte/r {$recipientName} <br />\n<br />\nIhr Zeitschriftenbericht für {$month}, {$year} ist jetzt verfügbar. Ihre wichtigsten Statistiken für diesen Monat:<br />\n<ul>\n	<li>Neue Einreichungen diesen Monat: {$newSubmissions}</li>\n	<li>Abgelehnte Einreichungen diesen Monat: {$declinedSubmissions}</li>\n	<li>Angenommene Einsendungen in diesem Monat: {$acceptedSubmissions}</li>\n	<li>Gesamtanzahl der Einreichungen im System: {$totalSubmissions}</li>\n</ul>\nMelden Sie sich bei der Zeitschrift an, um detailliertere <a href=\"{$editorialStatsLink}\">Redaktionstrends</a> und <a href=\"{$publicationStatsLink}\">Statistiken zu veröffentlichten Artikeln</a> anzuzeigen. Eine vollständige Kopie der redaktionellen Trends für diesen Monat ist beigefügt.<br />\n<br />\nMit freundlichen Grüßen,<br />\n{$contextSignature}'),
(100, 'STATISTICS_REPORT_NOTIFICATION', 'en', 'Statistics Report Notification', 'Editorial activity for {$month}, {$year}', '\n{$recipientName}, <br />\n<br />\nYour journal health report for {$month}, {$year} is now available. Your key stats for this month are below.<br />\n<ul>\n	<li>New submissions this month: {$newSubmissions}</li>\n	<li>Declined submissions this month: {$declinedSubmissions}</li>\n	<li>Accepted submissions this month: {$acceptedSubmissions}</li>\n	<li>Total submissions in the system: {$totalSubmissions}</li>\n</ul>\nLogin to the journal to view more detailed <a href=\"{$editorialStatsLink}\">editorial trends</a> and <a href=\"{$publicationStatsLink}\">published article stats</a>. A full copy of this month\'s editorial trends is attached.<br />\n<br />\nSincerely,<br />\n{$contextSignature}'),
(101, 'ANNOUNCEMENT', 'de', 'Neue Mitteilung', '{$announcementTitle}', '<b>{$announcementTitle}</b><br />\n<br />\n{$announcementSummary}<br />\n<br />\nBesuchen Sie unsere Website, um die <a href=\"{$announcementUrl}\">vollständige Ankündigung</a> zu lesen.'),
(102, 'ANNOUNCEMENT', 'en', 'New Announcement', '{$announcementTitle}', '<b>{$announcementTitle}</b><br />\n<br />\n{$announcementSummary}<br />\n<br />\nVisit our website to read the <a href=\"{$announcementUrl}\">full announcement</a>.'),
(103, 'DISCUSSION_NOTIFICATION_SUBMISSION', 'de', 'Diskussion (Einreichung)', 'Eine Nachricht bezüglich {$contextName}', 'Bitte geben Sie Ihre Nachricht ein.'),
(104, 'DISCUSSION_NOTIFICATION_SUBMISSION', 'en', 'Discussion (Submission)', 'A message regarding {$contextName}', 'Please enter your message.'),
(105, 'DISCUSSION_NOTIFICATION_REVIEW', 'de', 'Diskussion (Begutachtung)', 'Eine Nachricht bezüglich {$contextName}', 'Bitte geben Sie Ihre Nachricht ein.'),
(106, 'DISCUSSION_NOTIFICATION_REVIEW', 'en', 'Discussion (Review)', 'A message regarding {$contextName}', 'Please enter your message.'),
(107, 'DISCUSSION_NOTIFICATION_COPYEDITING', 'de', 'Diskussion (Lektorat)', 'Eine Nachricht bezüglich {$contextName}', 'Bitte geben Sie Ihre Nachricht ein.'),
(108, 'DISCUSSION_NOTIFICATION_COPYEDITING', 'en', 'Discussion (Copyediting)', 'A message regarding {$contextName}', 'Please enter your message.'),
(109, 'DISCUSSION_NOTIFICATION_PRODUCTION', 'de', 'Diskussion (Produktion)', 'Eine Nachricht bezüglich {$contextName}', 'Bitte geben Sie Ihre Nachricht ein.'),
(110, 'DISCUSSION_NOTIFICATION_PRODUCTION', 'en', 'Discussion (Production)', 'A message regarding {$contextName}', 'Please enter your message.'),
(111, 'COPYEDIT_REQUEST', 'de', 'Lektorat anfragen', 'Einreichung {$submissionId} ist bereit für die Bearbeitung von {$contextAcronym}', '<p>Sehr geehrte/r {$recipientName},</p><p>Ein neuer Beitrag ist bereit für das Lektorat:</p><p><a href\"{$submissionUrl}\">{$submissionId} — {$submissionTitle}</a><br />{$contextName}</p><p>Bitte befolgen Sie diese Schritte, um diese Aufgabe zu erledigen:</p><ol><li>Klicken Sie auf die URL für die Einreichung unten.</li><li>Öffnen Sie alle Dateien, die unter Entwurfsdateien verfügbar sind, und bearbeiten Sie die Dateien. Benutzen Sie den Bereich Diskussionen zum Lektorat, wenn Sie mit dem/den Redakteur(en) oder Autor(en) Kontakt aufnehmen möchten.</li><li>Speichern Sie die bearbeitete(n) Datei(en) und laden Sie sie in das Bedienfeld Lektorierte Dateien hoch.</li><li>Benutzen Sie die Lektorats-Diskussionen, um den/die Redakteur/in zu benachrichtigen, dass alle Dateien vorbereitet wurden und der Produktionsprozess beginnen kann.</li></ol><p>Wenn Sie diese Arbeit zu diesem Zeitpunkt nicht übernehmen können oder Fragen haben, wenden Sie sich bitte an mich. Vielen Dank für Ihren Beitrag zu {$contextName}.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(112, 'COPYEDIT_REQUEST', 'en', 'Request Copyedit', 'Submission {$submissionId} is ready to be copyedited for {$contextAcronym}', '<p>Dear {$recipientName},</p><p>A new submission is ready to be copyedited:</p><p><a href\"{$submissionUrl}\">{$submissionId} — \"{$submissionTitle}\"</a><br />{$contextName}</p><p>Please follow these steps to complete this task:</p><ol><li>Click on the Submission URL below.</li><li>Open any files available under Draft Files and edit the files. Use the Copyediting Discussions area if you need to contact the editor(s) or author(s).</li><li>Save the copyedited file(s) and upload them to the Copyedited panel.</li><li>Use the Copyediting Discussions to notify the editor(s) that all files have been prepared, and that the Production process may begin.</li></ol><p>If you are unable to undertake this work at this time or have any questions, please contact me. Thank you for your contribution to {$contextName}.</p><p>Kind regards,</p>{$signature}'),
(113, 'EDITOR_ASSIGN_SUBMISSION', 'de', 'Redakteur/in zuweisen', 'Sie wurden als Redakteur/in von einer Einreichung bei {$contextName} zugewiesen', '<p>Sehr geehrte/r {$recipientName},</p><p>Die folgende Einreichung wurde Ihnen zur redaktionellen Bearbeitung zugewiesen. </p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>Wenn Sie der Meinung sind, dass die Einreichung für die Zeitschrift {$contextName} relevant ist, leiten Sie die Einreichung bitte zur Begutachtung weiter, indem Sie \"In die Begutachtung schicken\" wählen und dann Gutachter zuweisen, indem Sie auf \"Gutachter/in hinzufügen\" klicken. </p><p>Wenn die Einreichung nicht für diese Zeitschrift geeignet ist, lehnen Sie die Einreichung bitte ab.</p><p>Danke im Voraus.</p><p>Mit freundlichen Grüßen,</p>{$contextSignature}'),
(114, 'EDITOR_ASSIGN_SUBMISSION', 'en', 'Assign Editor', 'You have been assigned as an editor on a submission to {$contextName}', '<p>Dear {$recipientName},</p><p>The following submission has been assigned to you to see through the editorial process.</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>If you find the submission to be relevant for {$contextName}, please forward the submission to the review stage by selecting \"Send to Review\" and then assign reviewers by clicking \"Add Reviewer\".</p><p>If the submission is not appropriate for this journal, please decline the submission.</p><p>Thank you in advance.</p><p>Kind regards,</p>{$contextSignature}'),
(115, 'EDITOR_ASSIGN_REVIEW', 'de', 'Redakteur/in zuweisen', 'Sie wurden als Redakteur/in von einer Einreichung bei {$contextName} zugewiesen', '<p>Sehr geehrte/r {$recipientName},</p><p>Die folgende Einreichung wurde Ihnen zur Begutachtung zugewiesen.</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>Bitte melden Sie sich an, um <a href=\"{$submissionUrl}\">die Einreichung einzusehen</a> und qualifizierte Gutachter/innen zuzuweisen. Sie können eine/n Gutachter/in zuweisen, indem Sie auf \"Gutachter/in hinzufügen\" klicken.</p><p>Danke im Voraus.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(116, 'EDITOR_ASSIGN_REVIEW', 'en', 'Assign Editor', 'You have been assigned as an editor on a submission to {$contextName}', '<p>Dear {$recipientName},</p><p>The following submission has been assigned to you to see through the peer review process.</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>Please login to <a href=\"{$submissionUrl}\">view the submission</a> and assign qualified reviewers. You can assign a reviewer by clicking \"Add Reviewer\".</p><p>Thank you in advance.</p><p>Kind regards,</p>{$signature}'),
(117, 'EDITOR_ASSIGN_PRODUCTION', 'de', 'Redakteur/in zuweisen', 'Sie wurden als Redakteur/in von einer Einreichung bei {$contextName} zugewiesen', '<p>Sehr geehrte/r {$recipientName},</p><p>Die folgende Einreichung wurde Ihnen für die Produktionsphase zugewiesen.</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>Bitte melden Sie sich an, um <a href=\"{$submissionUrl}\">die Einreichung einzusehen</a>. Sobald die produktionsfertigen Dateien vorliegen, laden Sie sie unter <strong>Publikation > Fahnen</strong> hoch. Klicken Sie dann auf die Schaltfläche <strong>Zur Veröffentlichung vorsehen</strong>.</p><p>Vielen Dank im Voraus.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(118, 'EDITOR_ASSIGN_PRODUCTION', 'en', 'Assign Editor', 'You have been assigned as an editor on a submission to {$contextName}', '<p>Dear {$recipientName},</p><p>The following submission has been assigned to you to see through the production stage.</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>Please login to <a href=\"{$submissionUrl}\">view the submission</a>. Once production-ready files are available, upload them under the <strong>Publication > Galleys</strong> section. Then schedule the work for publication by clicking the <strong>Schedule for Publication</strong> button.</p><p>Thank you in advance.</p><p>Kind regards,</p>{$signature}'),
(119, 'LAYOUT_REQUEST', 'de', 'Für die Produktion bereit', 'Die Einreichung {$submissionId} ist bereit für die Produktion bei {$contextAcronym}', '<p>Liebe/r {$recipientName},</p><p>eine neue Einreichung steht für die Layout-Bearbeitung bereit:</p><p><a href=\"{$submissionUrl}\">{$submissionId} - {$submissionTitle}</a><br />{$contextName}</p><ol><li>Klicken Sie auf die obige Einreichungs-URL.</li><li>Laden Sie die produktionsfertigen Dateien herunter und verwenden Sie sie, um die Druckfahnen gemäß den Standards der Zeitschrift zu erstellen. </li><li>Laden Sie die Druckfahnen in den Publikationsbereich der Einreichung hoch.</li><li>Benutzen Sie die Produktionsdiskussionen, um der Redaktion mitzuteilen, dass die Druckfahnen fertig sind.</li></ol><p>Wenn Sie diese Arbeit zu diesem Zeitpunkt noch nicht erledigen können oder Fragen haben, wenden Sie sich bitte an mich. Vielen Dank für Ihren Beitrag zu dieser Zeitschrift.</p><p>Mit freundlichen Grüßen,</p>{$signature}'),
(120, 'LAYOUT_REQUEST', 'en', 'Ready for Production', 'Submission {$submissionId} is ready for production at {$contextAcronym}', '<p>Dear {$recipientName},</p><p>A new submission is ready for layout editing:</p><p><a href=\"{$submissionUrl}\">{$submissionId} — {$submissionTitle}</a><br />{$contextName}</p><ol><li>Click on the Submission URL above.</li><li>Download the Production Ready files and use them to create the galleys according to the journal\'s standards.</li><li>Upload the galleys to the Publication section of the submission.</li><li>Use the  Production Discussions to notify the editor that the galleys are ready.</li></ol><p>If you are unable to undertake this work at this time or have any questions, please contact me. Thank you for your contribution to this journal.</p><p>Kind regards,</p>{$signature}'),
(121, 'LAYOUT_COMPLETE', 'de', 'Fahnen vollständig', 'Fahnen vollständig', '<p>Sehr geehrte/r {$recipientName},</p><p>die Druckfahnen für die folgende Einreichung wurden nun vorbereitet und sind bereit für die endgültige Überprüfung.</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$contextName}</p><p>Wenn Sie Fragen haben, kontaktieren Sie mich bitte.</p><p>Mit freundlichen Grüßen,</p><p>{$signature}</p>'),
(122, 'LAYOUT_COMPLETE', 'en', 'Galleys Complete', 'Galleys Complete', '<p>Dear {$recipientName},</p><p>Galleys have now been prepared for the following submission and are ready for final review.</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$contextName}</p><p>If you have any questions, please contact me.</p><p>Kind regards,</p><p>{$signature}</p>'),
(123, 'VERSION_CREATED', 'de', 'Version erstellt', 'Eine neue Version wurde für {$submissionTitle} erstellt', '<p>Sehr geehrte/r {$recipientName}, </p><p>dies ist eine automatische Nachricht, die Sie darüber informiert, dass eine neue Version Ihres Beitrags {$submissionTitle} erstellt wurde. Sie können diese Version in Ihrem Dashboard für die Einreichung unter folgendem Link einsehen:</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a></p><hr><p>Dies ist eine automatische E-Mail, gesendet von <a href=\"{$contextUrl}\">{$contextName}</a>.</p>'),
(124, 'VERSION_CREATED', 'en', 'Version Created', 'A new version was created for \"{$submissionTitle}\"', '<p>Dear {$recipientName}, </p><p>This is an automated message to inform you that a new version of your submission, \"{$submissionTitle}\", was created. You can view this version from your submission dashboard at the following link:</p><p><a href=\"{$submissionUrl}\">\"{$submissionTitle}\"</a></p><hr><p>This is an automatic email sent from <a href=\"{$contextUrl}\">{$contextName}</a>.</p>'),
(125, 'EDITORIAL_REMINDER', 'de', 'Redaktionserinnerung', 'Ausstehende redaktionelle Aufgaben für {$contextName}', '<p>Sehr geehrte/r {$recipientName},</p><p>Sie sind derzeit {$numberOfSubmissions} Einreichung(en) zugewiesen in <a href=\"{$contextUrl}\">{$contextName}</a>. Die folgenden Einsendungen <b>warten auf Ihre Antwort</b></p>{$outstandingTasks}<p>Sehen Sie alle Ihre Aufgaben an in Ihrem <a href=\"{$submissionsUrl}\">Dashboard der Einreichungen</a>.</p><p>Wenn Sie Fragen zu Ihren Aufgaben haben, wenden Sie sich bitte an {$contactName} unter {$contactEmail}.</p>'),
(126, 'EDITORIAL_REMINDER', 'en', 'Editorial Reminder', 'Outstanding editorial tasks for {$contextName}', '<p>Dear {$recipientName},</p><p>You are currently assigned to {$numberOfSubmissions} submissions in <a href=\"{$contextUrl}\">{$contextName}</a>. The following submissions are <b>waiting for your response</b>.</p>{$outstandingTasks}<p>View all of your assignments in your <a href=\"{$submissionsUrl}\">submission dashboard</a>.</p><p>If you have any questions about your assignments, please contact {$contactName} at {$contactEmail}.</p>'),
(127, 'SUBMISSION_SAVED_FOR_LATER', 'de', 'Einreichung für später gespeichert', 'Setzen Sie Ihre Einreichung bei {$contextName} fort', '<p>Sehr geehrte/r {$recipientName},</p><p>Die Angaben zu Ihrem Beitrag sind in unserem System gespeichert, aber noch nicht zur Prüfung eingereicht worden. Sie können jederzeit zurückkehren, um Ihre Einreichung zu vervollständigen, indem Sie dem unten stehenden Link folgen.</p><p><a href=\"{$submissionWizardUrl}\">{$authorsShort} — {$submissionTitle}</a></p><hr><p>Dies ist eine automatische E-Mail von <a href=\"{$contextUrl}\">{$contextName}</a>.</p>'),
(128, 'SUBMISSION_SAVED_FOR_LATER', 'en', 'Submission Saved for Later', 'Resume your submission to {$contextName}', '<p>Dear {$recipientName},</p><p>Your submission details have been saved in our system, but it has not yet been submitted for consideration. You can return to complete your submission at any time by following the link below.</p><p><a href=\"{$submissionWizardUrl}\">{$authorsShort} — \"{$submissionTitle}\"</a></p><hr><p>This is an automated email from <a href=\"{$contextUrl}\">{$contextName}</a>.</p>'),
(129, 'SUBMISSION_NEEDS_EDITOR', 'de', 'Einreichung benötigt Redakteur/in', 'Einer neuen Einreichung muss ein Redakteur/eine Redakteurin zugewiesen werden: {$submissionTitle}', '<p>Sehr geehrte/r {$recipientName},</p><p>Der folgende Beitrag wurde eingereicht und es wurde kein/e Redakteur/in zugewiesen.</p><p><a href=\"{$submissionUrl}\">{$submissionTitle}</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>Bitte weisen Sie eine/n Redakteur/in zu, der für die Einreichung verantwortlich sein wird, indem Sie auf den Titel oben klicken, und dann eine/n Redakteur/in unter der Rubrik Teilnehmer zuweisen.</p><hr><p>Dies ist eine automatische E-Mail von <a href=\"{$contextUrl}\">{$contextName}</a>.</p>'),
(130, 'SUBMISSION_NEEDS_EDITOR', 'en', 'Submission Needs Editor', 'A new submission needs an editor to be assigned: \"{$submissionTitle}\"', '<p>Dear {$recipientName},</p><p>The following submission has been submitted and there is no editor assigned.</p><p><a href=\"{$submissionUrl}\">\"{$submissionTitle}\"</a><br />{$authors}</p><p><b>Abstract</b></p>{$submissionAbstract}<p>Please assign an editor who will be responsible for the submission by clicking the title above and assigning an editor under the Participants section.</p><hr><p>This is an automated email from <a href=\"{$contextUrl}\">{$contextName}</a>.</p>'),
(131, 'PAYMENT_REQUEST_NOTIFICATION', 'de', 'Zahlungsanfrage', 'Benachrichtigung über eine Zahlungsanforderung', '<p>Sehr geehrte/r {$recipientName},</p><p>herzlichen Glückwunsch zur Annahme Ihrer Einreichung {$submissionTitle} bei {$contextName}. Nachdem Ihre Einreichung angenommen wurde, möchten wir Sie nun um die Zahlung der Veröffentlichungsgebühr bitten.</p><p>Diese Gebühr deckt die Produktionskosten für die Veröffentlichung Ihrer Einreichung. Um die Zahlung vorzunehmen, besuchen Sie bitte <a href=\"{$queuedPaymentUrl}\">{$queuedPaymentUrl}</a>.</p><p>Wenn Sie Fragen haben, lesen Sie bitte unsere <a href=\"{$submissionGuidelinesUrl}\">Einreichungsrichtlinien</a>.</p>'),
(132, 'PAYMENT_REQUEST_NOTIFICATION', 'en', 'Payment Request', 'Payment Request Notification', '<p>Dear {$recipientName},</p><p>Congratulations on the acceptance of your submission, {$submissionTitle}, to {$contextName}. Now that your submission has been accepted, we would like to request payment of the publication fee.</p><p>This fee covers the production costs of bringing your submission to publication. To make the payment, please visit <a href=\"{$queuedPaymentUrl}\">{$queuedPaymentUrl}</a>.</p><p>If you have any questions, please see our <a href=\"{$submissionGuidelinesUrl}\">Submission Guidelines</a></p>'),
(133, 'CHANGE_EMAIL', 'de', '', '', ''),
(134, 'CHANGE_EMAIL', 'en', 'Change Email Address Invitation', 'Confirm account contact email change request', '<p>Dear {$recipientName},</p><p>You are receiving this email because someone has requested a change of your email to {$newEmail}.</p><p>If you have made this request please <a href=\"{$acceptInvitationUrl}\">confirm</a> the email change.</p><p>You can always <a href=\"{$declineInvitationUrl}\">reject</a> this email change.</p><p>Please feel free to contact me with any questions about the submission or the review process.</p><p>Kind regards,</p>{$siteContactName}'),
(135, 'ORCID_COLLECT_AUTHOR_ID', 'de', '', 'ORCID Zugriff erbeten', 'Liebe/r {$recipientName},<br/>\n<br/>\nSie sind als Autor/in eines eingereichten Beitrags bei der Zeitschrift {$contextName} benannt worden.<br/>\nUm Ihre Autor/innenschaft zu bestätigen, geben Sie bitte Ihre ORCID iD für diese Einreichung an, indem Sie den unten angegebenen Link aufrufen.<br/>\n<br/>\n<a href=\"{$authorOrcidUrl}\"><img id=\"orcid-id-logo\" src=\"https://orcid.org/sites/default/files/images/orcid_16x16.png\" width=\'16\' height=\'16\' alt=\"ORCID iD icon\" style=\"display: block; margin: 0 .5em 0 0; padding: 0; float: left;\"/>ORCID iD anlegen oder verknüpfen</a><br/>\n<br/>\n<a href=\"{$orcidAboutUrl}\">Mehr Informationen zu ORCID</a><br/>\n<br/>\nWenn Sie Fragen dazu haben, antworten Sie einfach auf diese E-Mail.<br/>\n<br/>\n{$principalContactSignature}<br/>\n'),
(136, 'ORCID_COLLECT_AUTHOR_ID', 'en', 'orcidCollectAuthorId', 'Submission ORCID', 'Dear {$recipientName},<br/>\n<br/>\nYou have been listed as an author on a manuscript submission to {$contextName}.<br/>\nTo confirm your authorship, please add your ORCID id to this submission by visiting the link provided below.<br/>\n<br/>\n<a href=\"{$authorOrcidUrl}\"><img id=\"orcid-id-logo\" src=\"https://info.orcid.org/wp-content/uploads/2020/12/ORCIDiD_icon16x16.png\" width=\'16\' height=\'16\' alt=\"ORCID iD icon\" style=\"display: block; margin: 0 .5em 0 0; padding: 0; float: left;\"/>Register or connect your ORCID iD</a><br/>\n<br/>\n<br>\n<a href=\"{$orcidAboutUrl}\">More information about ORCID at {$contextName}</a><br/>\n<br/>\nIf you have any questions, please contact me.<br/>\n<br/>\n{$principalContactSignature}<br/>\n');
INSERT INTO `email_templates_default_data` (`email_templates_default_data_id`, `email_key`, `locale`, `name`, `subject`, `body`) VALUES
(137, 'ORCID_REQUEST_AUTHOR_AUTHORIZATION', 'de', '', 'ORCID Zugriff erbeten', 'Liebe/r {$recipientName},<br>\n<br>\nSie sind als Autor/in des eingereichten Beitrags \"{$submissionTitle}\" bei der Zeitschrift {$contextName} benannt worden.<br>\n<br>\nBitte gestatten Sie uns Ihre ORCID iD, falls vorhanden, zu diesem Beitrag hinzuzufügen, sowie ihr ORCID-Profil bei Veröffentlichung des Beitrags zu aktualisieren.<br>\nDazu folgen Sie dem unten stehenden Link zur offiziellen ORCID-Seite, melden sich mit Ihren Daten an und authorisieren den Zugriff, indem\nSie den Anweisungen auf der Seite folgen.<br>\n<a href=\"{$authorOrcidUrl}\"><img id=\"orcid-id-logo\" src=\"https://info.orcid.org/wp-content/uploads/2020/12/ORCIDiD_icon16x16.png\" width=\'16\' height=\'16\' alt=\"ORCID iD icon\" style=\"display: block; margin: 0 .5em 0 0; padding: 0; float: left;\"/>ORCID id anlegen oder verknüpfen</a><br/>\n<br>\n<a href=\"{$orcidAboutUrl}\">Mehr Informationen zu ORCID</a><br />\n<br>\nWenn Sie Fragen dazu haben, antworten Sie einfach auf diese E-Mail.<br>\n<br>\n{$principalContactSignature}<br>\n'),
(138, 'ORCID_REQUEST_AUTHOR_AUTHORIZATION', 'en', 'orcidRequestAuthorAuthorization', 'Requesting ORCID record access', 'Dear {$recipientName},<br>\n<br>\nYou have been listed as an author on the manuscript submission \"{$submissionTitle}\" to {$contextName}.\n<br>\n<br>\nPlease allow us to add your ORCID id to this submission and also to add the submission to your ORCID profile on publication.<br>\nVisit the link to the official ORCID website, login with your profile and authorize the access by following the instructions.<br>\n<br>\n<a href=\"{$authorOrcidUrl}\" style=\"display: inline-flex; align-items: center; background-color: white; text-align: center; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: 2px solid #d7d4d4;\"><img id=\"orcid-id-logo\" src=\"https://info.orcid.org/wp-content/uploads/2020/12/ORCIDiD_icon16x16.png\" width=\'16\' height=\'16\' alt=\"ORCID iD icon\" style=\"display: block; margin: 0 .5em 0 0; padding: 0; float: left;\"/>Register or Connect your ORCID iD</a><br/>\n<br>\n<br>\nClick here to verify your account with ORCID: <a href=\"{$authorOrcidUrl}\">{$authorOrcidUrl}.</a>\n<br>\n<br>\n<a href=\"{$orcidAboutUrl}\">More about ORCID at {$contextName}</a><br/>\n<br>\n<br>\nIf you have any questions, please contact me.<br>\n<br>\n{$principalContactSignature}<br>\n'),
(139, 'USER_ROLE_ASSIGNMENT_INVITATION', 'de', '', '', ''),
(140, 'USER_ROLE_ASSIGNMENT_INVITATION', 'en', 'User Invited to Role Notification', 'You are invited to new roles', '<div class=\'email-container\'>    <div class=\'email-header\'>        <h2>Invitation to New Role</h2>    </div>    <div class=\'email-content\'>        <p>Dear {$recipientName},</p>        <p>In light of your expertise, you have been invited by {$inviterName} to take on new roles at {$contextName}</p>        <p>At {$contextName}, we value your privacy. As such, we have taken steps to ensure that we are fully GDPR compliant. These steps include you being accountable to enter your own data and choosing who can see what information. For additional information on how we handled your data, please refer to our Privacy Policy.</p>        <div>{$existingRoles}</div>        <div>{$rolesAdded}</div>        <p>On accepting the invite, you will be redirected to {$contextName}.</p>        <p>Feel free to contact me with any questions about the process.</p>        <p><a href=\'{$acceptUrl}\' class=\'btn btn-accept\'>Accept Invitation</a></p>        <p><a href=\'{$declineUrl}\' class=\'btn btn-decline\'>Decline Invitation</a></p>        <p>Kind regards,</p>        <p>{$contextName}</p>    </div></div><style>{$emailTemplateStyle}</style>'),
(141, 'USER_ROLE_END', 'de', '', '', ''),
(142, 'USER_ROLE_END', 'en', 'User Role Ended Notification', 'You have been removed from a role', '<div class=\'email-container\'>    <div class=\'email-header\'>        <h2>Removed from a Role</h2>    </div>    <div class=\'email-content\'>        <p>Dear {$recipientName},</p>        <p>Thank you very much for your participation in the role of {$roleRemoved} at {$contextName}.</p>        <p>This is a notice to let you know that you have been removed from the following role at {$contextName}: <b>{$roleRemoved}</b>.</p>        <p>Your account with {$contextName} is still active and any other roles you previously held are still active.</p>        <p>Feel free to contact me with any questions about the process.</p>        <p>Kind regards,</p>        <p>{$contextName}</p>    </div></div><style>{$emailTemplateStyle}</style>'),
(143, 'ORCID_REQUEST_UPDATE_SCOPE', 'de', '', '', ''),
(144, 'ORCID_REQUEST_UPDATE_SCOPE', 'en', 'orcidRequestUpdateScope', 'Requesting updated ORCID record access', 'Dear {$recipientName},<br>\n<br>\nYou are listed as a contributor (author or reviewer) on the manuscript submission \"{$submissionTitle}\" to {$contextName}.\n<br>\n<br>\nYou have previously authorized {$contextName} to list your ORCID id on the site, and we require updateded permissions to add your contribution to your ORCID profile.<br>\nVisit the link to the official ORCID website, login with your profile and authorize the access by following the instructions.<br>\n<br>\n<a href=\"{$authorOrcidUrl}\" style=\"display: inline-flex; align-items: center; background-color: white; text-align: center; padding: 10px 20px; text-decoration: none; border-radius: 5px; border: 2px solid #d7d4d4;\"><img id=\"orcid-id-logo\" src=\"https://info.orcid.org/wp-content/uploads/2020/12/ORCIDiD_icon16x16.png\" width=\'16\' height=\'16\' alt=\"ORCID iD icon\" style=\"display: block; margin: 0 .5em 0 0; padding: 0; float: left;\"/>Register or Connect your ORCID iD</a><br/>\n<br>\n<br>\nClick here to update your account with ORCID: <a href=\"{$authorOrcidUrl}\">{$authorOrcidUrl}.</a>\n<br>\n<br>\n<a href=\"{$orcidAboutUrl}\">More about ORCID at {$contextName}</a><br/>\n<br>\n<br>\nIf you have any questions, please contact me.<br>\n<br>\n{$principalContactSignature}<br>\n'),
(145, 'MANUAL_PAYMENT_NOTIFICATION', 'de', 'Manuelle Zahlungsbenachrichtigung', 'Benachrichtigung über manuelle Zahlung', 'Eine manuelle Zahlung für die Zeitschrift {$contextName} und den/die Benutzer/in &quot;{$senderUsername}&quot;) muss bearbeitet werden.<br />\n<br />\nGezahlt werden soll für &quot;{$paymentName}&quot;.<br />\nDie Kosten betragen {$paymentAmount} ({$paymentCurrencyCode}).<br />\n<br />\nDiese E-Mail wurde vom OJS-Plugin Manuelle Gebührenzahlung erzeugt.'),
(146, 'MANUAL_PAYMENT_NOTIFICATION', 'en', 'Manual Payment Notify', 'Manual Payment Notification', 'A manual payment needs to be processed for the journal {$contextName} and the user &quot;{$senderUsername}&quot;.<br />\n<br />\nThe item being paid for is &quot;{$paymentName}&quot;.<br />\nThe cost is {$paymentAmount} ({$paymentCurrencyCode}).<br />\n<br />\nThis email was generated by Open Journal Systems\' Manual Payment plugin.');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates_settings`
--

CREATE TABLE `email_templates_settings` (
  `email_template_setting_id` bigint UNSIGNED NOT NULL,
  `email_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about custom email templates, including localized properties such as the subject and body.';

-- --------------------------------------------------------

--
-- Table structure for table `event_log`
--

CREATE TABLE `event_log` (
  `log_id` bigint NOT NULL,
  `assoc_type` bigint NOT NULL,
  `assoc_id` bigint NOT NULL,
  `user_id` bigint DEFAULT NULL COMMENT 'NULL if it''s system or automated event',
  `date_logged` datetime NOT NULL,
  `event_type` bigint DEFAULT NULL,
  `message` text,
  `is_translated` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A log of all events related to an object like a submission.';

-- --------------------------------------------------------

--
-- Table structure for table `event_log_settings`
--

CREATE TABLE `event_log_settings` (
  `event_log_setting_id` bigint UNSIGNED NOT NULL,
  `log_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Data about an event log entry. This data is commonly used to display information about an event to a user.';

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A log of all failed jobs.';

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `file_id` bigint UNSIGNED NOT NULL,
  `path` varchar(255) NOT NULL,
  `mimetype` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Records information in the database about files tracked by the system, linking them to the local filesystem.';

--
-- Dumping data for table `files`
--

INSERT INTO `files` (`file_id`, `path`, `mimetype`) VALUES
(2, 'journals/1/articles/2/699f8405bd577.pdf', 'application/pdf'),
(3, 'journals/1/articles/3/699f8de30e23a.pdf', 'application/pdf'),
(4, 'journals/1/articles/4/699f9061016a7.pdf', 'application/pdf'),
(5, 'journals/1/articles/5/699f9295ac0b8.pdf', 'application/pdf'),
(6, 'journals/1/articles/7/699f97ddbaae8.pdf', 'application/pdf'),
(7, 'journals/1/articles/8/699f9a93ecbb4.pdf', 'application/pdf'),
(8, 'journals/1/articles/9/699f9ca313b99.pdf', 'application/pdf'),
(9, 'journals/1/articles/10/699f9ea57e85a.pdf', 'application/pdf');

-- --------------------------------------------------------

--
-- Table structure for table `filters`
--

CREATE TABLE `filters` (
  `filter_id` bigint NOT NULL,
  `filter_group_id` bigint NOT NULL,
  `context_id` bigint DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `class_name` varchar(255) DEFAULT NULL,
  `is_template` smallint NOT NULL DEFAULT '0',
  `parent_filter_id` bigint DEFAULT NULL,
  `seq` bigint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Filters represent a transformation of a supported piece of data from one form to another, such as a PHP object into an XML document.';

--
-- Dumping data for table `filters`
--

INSERT INTO `filters` (`filter_id`, `filter_group_id`, `context_id`, `display_name`, `class_name`, `is_template`, `parent_filter_id`, `seq`) VALUES
(1, 1, NULL, 'Crossref XML issue export', 'APP\\plugins\\generic\\crossref\\filter\\IssueCrossrefXmlFilter', 0, NULL, 0),
(2, 2, NULL, 'Crossref XML article export', 'APP\\plugins\\generic\\crossref\\filter\\ArticleCrossrefXmlFilter', 0, NULL, 0),
(3, 3, NULL, 'DataCite XML export', 'APP\\plugins\\generic\\datacite\\filter\\DataciteXmlFilter', 0, NULL, 0),
(4, 4, NULL, 'DataCite XML export', 'APP\\plugins\\generic\\datacite\\filter\\DataciteXmlFilter', 0, NULL, 0),
(5, 5, NULL, 'DataCite XML export', 'APP\\plugins\\generic\\datacite\\filter\\DataciteXmlFilter', 0, NULL, 0),
(6, 6, NULL, 'Extract metadata from a(n) Submission', 'APP\\plugins\\metadata\\dc11\\filter\\Dc11SchemaArticleAdapter', 0, NULL, 0),
(7, 7, NULL, 'DOAJ XML export', 'APP\\plugins\\importexport\\doaj\\filter\\DOAJXmlFilter', 0, NULL, 0),
(8, 8, NULL, 'DOAJ JSON export', 'APP\\plugins\\importexport\\doaj\\filter\\DOAJJsonFilter', 0, NULL, 0),
(9, 9, NULL, 'Native XML submission export', 'APP\\plugins\\importexport\\native\\filter\\ArticleNativeXmlFilter', 0, NULL, 0),
(10, 10, NULL, 'Native XML submission import', 'APP\\plugins\\importexport\\native\\filter\\NativeXmlArticleFilter', 0, NULL, 0),
(11, 11, NULL, 'Native XML issue export', 'APP\\plugins\\importexport\\native\\filter\\IssueNativeXmlFilter', 0, NULL, 0),
(12, 12, NULL, 'Native XML issue import', 'APP\\plugins\\importexport\\native\\filter\\NativeXmlIssueFilter', 0, NULL, 0),
(13, 13, NULL, 'Native XML issue galley export', 'APP\\plugins\\importexport\\native\\filter\\IssueGalleyNativeXmlFilter', 0, NULL, 0),
(14, 14, NULL, 'Native XML issue galley import', 'APP\\plugins\\importexport\\native\\filter\\NativeXmlIssueGalleyFilter', 0, NULL, 0),
(15, 15, NULL, 'Native XML author export', 'APP\\plugins\\importexport\\native\\filter\\AuthorNativeXmlFilter', 0, NULL, 0),
(16, 16, NULL, 'Native XML author import', 'APP\\plugins\\importexport\\native\\filter\\NativeXmlAuthorFilter', 0, NULL, 0),
(17, 18, NULL, 'Native XML submission file import', 'APP\\plugins\\importexport\\native\\filter\\NativeXmlArticleFileFilter', 0, NULL, 0),
(18, 17, NULL, 'Native XML submission file export', 'PKP\\plugins\\importexport\\native\\filter\\SubmissionFileNativeXmlFilter', 0, NULL, 0),
(19, 19, NULL, 'Native XML representation export', 'APP\\plugins\\importexport\\native\\filter\\ArticleGalleyNativeXmlFilter', 0, NULL, 0),
(20, 20, NULL, 'Native XML representation import', 'APP\\plugins\\importexport\\native\\filter\\NativeXmlArticleGalleyFilter', 0, NULL, 0),
(21, 21, NULL, 'Native XML Publication export', 'APP\\plugins\\importexport\\native\\filter\\PublicationNativeXmlFilter', 0, NULL, 0),
(22, 22, NULL, 'Native XML publication import', 'APP\\plugins\\importexport\\native\\filter\\NativeXmlPublicationFilter', 0, NULL, 0),
(23, 23, NULL, 'User XML user export', 'PKP\\plugins\\importexport\\users\\filter\\PKPUserUserXmlFilter', 0, NULL, 0),
(24, 24, NULL, 'User XML user import', 'PKP\\plugins\\importexport\\users\\filter\\UserXmlPKPUserFilter', 0, NULL, 0),
(25, 25, NULL, 'Native XML user group export', 'PKP\\plugins\\importexport\\users\\filter\\UserGroupNativeXmlFilter', 0, NULL, 0),
(26, 26, NULL, 'Native XML user group import', 'PKP\\plugins\\importexport\\users\\filter\\NativeXmlUserGroupFilter', 0, NULL, 0),
(27, 27, NULL, 'APP\\plugins\\importexport\\pubmed\\filter\\ArticlePubMedXmlFilter', 'APP\\plugins\\importexport\\pubmed\\filter\\ArticlePubMedXmlFilter', 0, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `filter_groups`
--

CREATE TABLE `filter_groups` (
  `filter_group_id` bigint NOT NULL,
  `symbolic` varchar(255) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `input_type` varchar(255) DEFAULT NULL,
  `output_type` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Filter groups are used to organized filters into named sets, which can be retrieved by the application for invocation.';

--
-- Dumping data for table `filter_groups`
--

INSERT INTO `filter_groups` (`filter_group_id`, `symbolic`, `display_name`, `description`, `input_type`, `output_type`) VALUES
(1, 'issue=>crossref-xml', 'plugins.importexport.crossref.displayName', 'plugins.importexport.crossref.description', 'class::classes.issue.Issue[]', 'xml::schema(https://www.crossref.org/schemas/crossref5.4.0.xsd)'),
(2, 'article=>crossref-xml', 'plugins.importexport.crossref.displayName', 'plugins.importexport.crossref.description', 'class::classes.submission.Submission[]', 'xml::schema(https://www.crossref.org/schemas/crossref5.4.0.xsd)'),
(3, 'issue=>datacite-xml', 'plugins.importexport.datacite.displayName', 'plugins.importexport.datacite.description', 'class::classes.issue.Issue', 'xml::schema(http://schema.datacite.org/meta/kernel-4/metadata.xsd)'),
(4, 'article=>datacite-xml', 'plugins.importexport.datacite.displayName', 'plugins.importexport.datacite.description', 'class::classes.submission.Submission', 'xml::schema(http://schema.datacite.org/meta/kernel-4/metadata.xsd)'),
(5, 'galley=>datacite-xml', 'plugins.importexport.datacite.displayName', 'plugins.importexport.datacite.description', 'class::lib.pkp.classes.galley.Galley', 'xml::schema(http://schema.datacite.org/meta/kernel-4/metadata.xsd)'),
(6, 'article=>dc11', 'plugins.metadata.dc11.articleAdapter.displayName', 'plugins.metadata.dc11.articleAdapter.description', 'class::classes.submission.Submission', 'metadata::APP\\plugins\\metadata\\dc11\\schema\\Dc11Schema(ARTICLE)'),
(7, 'article=>doaj-xml', 'plugins.importexport.doaj.displayName', 'plugins.importexport.doaj.description', 'class::classes.submission.Submission[]', 'xml::schema(plugins/importexport/doaj/doajArticles.xsd)'),
(8, 'article=>doaj-json', 'plugins.importexport.doaj.displayName', 'plugins.importexport.doaj.description', 'class::classes.submission.Submission', 'primitive::string'),
(9, 'article=>native-xml', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'class::classes.submission.Submission[]', 'xml::schema(plugins/importexport/native/native.xsd)'),
(10, 'native-xml=>article', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'xml::schema(plugins/importexport/native/native.xsd)', 'class::classes.submission.Submission[]'),
(11, 'issue=>native-xml', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'class::classes.issue.Issue[]', 'xml::schema(plugins/importexport/native/native.xsd)'),
(12, 'native-xml=>issue', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'xml::schema(plugins/importexport/native/native.xsd)', 'class::classes.issue.Issue[]'),
(13, 'issuegalley=>native-xml', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'class::classes.issue.IssueGalley[]', 'xml::schema(plugins/importexport/native/native.xsd)'),
(14, 'native-xml=>issuegalley', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'xml::schema(plugins/importexport/native/native.xsd)', 'class::classes.issue.IssueGalley[]'),
(15, 'author=>native-xml', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'class::classes.author.Author[]', 'xml::schema(plugins/importexport/native/native.xsd)'),
(16, 'native-xml=>author', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'xml::schema(plugins/importexport/native/native.xsd)', 'class::classes.author.Author[]'),
(17, 'SubmissionFile=>native-xml', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'class::lib.pkp.classes.submissionFile.SubmissionFile', 'xml::schema(plugins/importexport/native/native.xsd)'),
(18, 'native-xml=>SubmissionFile', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'xml::schema(plugins/importexport/native/native.xsd)', 'class::lib.pkp.classes.submissionFile.SubmissionFile[]'),
(19, 'article-galley=>native-xml', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'class::lib.pkp.classes.galley.Galley', 'xml::schema(plugins/importexport/native/native.xsd)'),
(20, 'native-xml=>ArticleGalley', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'xml::schema(plugins/importexport/native/native.xsd)', 'class::lib.pkp.classes.galley.Galley[]'),
(21, 'publication=>native-xml', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'class::classes.publication.Publication', 'xml::schema(plugins/importexport/native/native.xsd)'),
(22, 'native-xml=>Publication', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'xml::schema(plugins/importexport/native/native.xsd)', 'class::classes.publication.Publication[]'),
(23, 'user=>user-xml', 'plugins.importexport.users.displayName', 'plugins.importexport.users.description', 'class::PKP\\user\\User[]', 'xml::schema(lib/pkp/plugins/importexport/users/pkp-users.xsd)'),
(24, 'user-xml=>user', 'plugins.importexport.users.displayName', 'plugins.importexport.users.description', 'xml::schema(lib/pkp/plugins/importexport/users/pkp-users.xsd)', 'class::PKP\\user\\User[]'),
(25, 'usergroup=>user-xml', 'plugins.importexport.users.displayName', 'plugins.importexport.users.description', 'class::PKP\\userGroup\\UserGroup[]', 'xml::schema(lib/pkp/plugins/importexport/users/pkp-users.xsd)'),
(26, 'user-xml=>usergroup', 'plugins.importexport.native.displayName', 'plugins.importexport.native.description', 'xml::schema(lib/pkp/plugins/importexport/users/pkp-users.xsd)', 'class::PKP\\userGroup\\UserGroup[]'),
(27, 'article=>pubmed-xml', 'plugins.importexport.pubmed.displayName', 'plugins.importexport.pubmed.description', 'class::classes.submission.Submission[]', 'xml::dtd');

-- --------------------------------------------------------

--
-- Table structure for table `filter_settings`
--

CREATE TABLE `filter_settings` (
  `filter_setting_id` bigint UNSIGNED NOT NULL,
  `filter_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about filters, including localized content.';

-- --------------------------------------------------------

--
-- Table structure for table `genres`
--

CREATE TABLE `genres` (
  `genre_id` bigint NOT NULL,
  `context_id` bigint NOT NULL,
  `seq` bigint NOT NULL,
  `enabled` smallint NOT NULL DEFAULT '1',
  `category` bigint NOT NULL DEFAULT '1',
  `dependent` smallint NOT NULL DEFAULT '0',
  `supplementary` smallint NOT NULL DEFAULT '0',
  `required` smallint NOT NULL DEFAULT '0' COMMENT 'Whether or not at least one file of this genre is required for a new submission.',
  `entry_key` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='The types of submission files configured for each context, such as Article Text, Data Set, Transcript, etc.';

--
-- Dumping data for table `genres`
--

INSERT INTO `genres` (`genre_id`, `context_id`, `seq`, `enabled`, `category`, `dependent`, `supplementary`, `required`, `entry_key`) VALUES
(1, 1, 0, 1, 1, 0, 0, 1, 'SUBMISSION'),
(2, 1, 1, 1, 3, 0, 1, 0, 'RESEARCHINSTRUMENT'),
(3, 1, 2, 1, 3, 0, 1, 0, 'RESEARCHMATERIALS'),
(4, 1, 3, 1, 3, 0, 1, 0, 'RESEARCHRESULTS'),
(5, 1, 4, 1, 3, 0, 1, 0, 'TRANSCRIPTS'),
(6, 1, 5, 1, 3, 0, 1, 0, 'DATAANALYSIS'),
(7, 1, 6, 1, 3, 0, 1, 0, 'DATASET'),
(8, 1, 7, 1, 3, 0, 1, 0, 'SOURCETEXTS'),
(9, 1, 8, 1, 1, 1, 1, 0, 'MULTIMEDIA'),
(10, 1, 9, 1, 2, 1, 0, 0, 'IMAGE'),
(11, 1, 10, 1, 1, 1, 0, 0, 'STYLE'),
(12, 1, 11, 1, 3, 0, 1, 0, 'OTHER'),
(13, 1, 101, 1, 3, 0, 1, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `genre_settings`
--

CREATE TABLE `genre_settings` (
  `genre_setting_id` bigint UNSIGNED NOT NULL,
  `genre_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL COMMENT '(bool|int|float|string|object)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about file genres, including localized properties such as the genre name.';

--
-- Dumping data for table `genre_settings`
--

INSERT INTO `genre_settings` (`genre_setting_id`, `genre_id`, `locale`, `setting_name`, `setting_value`, `setting_type`) VALUES
(1, 1, 'en', 'name', 'Article Text', 'string'),
(2, 2, 'en', 'name', 'Research Instrument', 'string'),
(3, 3, 'en', 'name', 'Research Materials', 'string'),
(4, 4, 'en', 'name', 'Research Results', 'string'),
(5, 5, 'en', 'name', 'Transcripts', 'string'),
(6, 6, 'en', 'name', 'Data Analysis', 'string'),
(7, 7, 'en', 'name', 'Data Set', 'string'),
(8, 8, 'en', 'name', 'Source Texts', 'string'),
(9, 9, 'en', 'name', 'Multimedia', 'string'),
(10, 10, 'en', 'name', 'Image', 'string'),
(11, 11, 'en', 'name', 'HTML Stylesheet', 'string'),
(12, 12, 'en', 'name', 'Other', 'string'),
(13, 13, 'en', 'name', 'codecheck.yml', 'string');

-- --------------------------------------------------------

--
-- Table structure for table `highlights`
--

CREATE TABLE `highlights` (
  `highlight_id` bigint NOT NULL,
  `context_id` bigint DEFAULT NULL,
  `sequence` bigint NOT NULL,
  `url` varchar(2047) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Highlights are featured items that can be presented to users, for example on the homepage.';

-- --------------------------------------------------------

--
-- Table structure for table `highlight_settings`
--

CREATE TABLE `highlight_settings` (
  `highlight_setting_id` bigint UNSIGNED NOT NULL,
  `highlight_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about highlights, including localized properties like title and description.';

-- --------------------------------------------------------

--
-- Table structure for table `institutional_subscriptions`
--

CREATE TABLE `institutional_subscriptions` (
  `institutional_subscription_id` bigint NOT NULL,
  `subscription_id` bigint NOT NULL,
  `institution_id` bigint NOT NULL,
  `mailing_address` varchar(255) DEFAULT NULL,
  `domain` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A list of institutional subscriptions, linking a subscription with an institution.';

-- --------------------------------------------------------

--
-- Table structure for table `institutions`
--

CREATE TABLE `institutions` (
  `institution_id` bigint NOT NULL,
  `context_id` bigint NOT NULL,
  `ror` varchar(255) DEFAULT NULL COMMENT 'ROR (Research Organization Registry) ID',
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Institutions for statistics and subscriptions.';

-- --------------------------------------------------------

--
-- Table structure for table `institution_ip`
--

CREATE TABLE `institution_ip` (
  `institution_ip_id` bigint NOT NULL,
  `institution_id` bigint NOT NULL,
  `ip_string` varchar(40) NOT NULL,
  `ip_start` bigint NOT NULL,
  `ip_end` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Records IP address ranges and associates them with institutions.';

-- --------------------------------------------------------

--
-- Table structure for table `institution_settings`
--

CREATE TABLE `institution_settings` (
  `institution_setting_id` bigint UNSIGNED NOT NULL,
  `institution_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about institutions, including localized properties like names.';

-- --------------------------------------------------------

--
-- Table structure for table `invitations`
--

CREATE TABLE `invitations` (
  `invitation_id` bigint NOT NULL,
  `key_hash` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `user_id` bigint DEFAULT NULL,
  `inviter_id` bigint DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `status` enum('INITIALIZED','PENDING','ACCEPTED','DECLINED','CANCELLED') NOT NULL,
  `email` varchar(255) DEFAULT NULL COMMENT 'When present, the email address of the invitation recipient; when null, user_id must be set and the email can be fetched from the users table.',
  `context_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Invitations are sent to request a person (by email) to allow them to accept or reject an operation or position, such as a board membership or a submission peer review.';

-- --------------------------------------------------------

--
-- Table structure for table `issues`
--

CREATE TABLE `issues` (
  `issue_id` bigint NOT NULL,
  `journal_id` bigint NOT NULL,
  `volume` smallint DEFAULT NULL,
  `number` varchar(40) DEFAULT NULL,
  `year` smallint DEFAULT NULL,
  `published` smallint NOT NULL DEFAULT '0',
  `date_published` datetime DEFAULT NULL,
  `date_notified` datetime DEFAULT NULL,
  `last_modified` datetime DEFAULT NULL,
  `access_status` smallint NOT NULL DEFAULT '1',
  `open_access_date` datetime DEFAULT NULL,
  `show_volume` smallint NOT NULL DEFAULT '0',
  `show_number` smallint NOT NULL DEFAULT '0',
  `show_year` smallint NOT NULL DEFAULT '0',
  `show_title` smallint NOT NULL DEFAULT '0',
  `style_file_name` varchar(90) DEFAULT NULL,
  `original_style_file_name` varchar(255) DEFAULT NULL,
  `url_path` varchar(64) DEFAULT NULL,
  `doi_id` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A list of all journal issues, with identifying information like year, number, volume, etc.';

--
-- Dumping data for table `issues`
--

INSERT INTO `issues` (`issue_id`, `journal_id`, `volume`, `number`, `year`, `published`, `date_published`, `date_notified`, `last_modified`, `access_status`, `open_access_date`, `show_volume`, `show_number`, `show_year`, `show_title`, `style_file_name`, `original_style_file_name`, `url_path`, `doi_id`) VALUES
(1, 1, 1, '1', 2026, 1, '2026-02-26 02:34:25', NULL, '2026-02-26 02:34:25', 1, NULL, 1, 1, 1, 1, NULL, NULL, NULL, NULL),
(2, 1, 1, '2', 2026, 1, '2026-02-26 02:34:32', NULL, '2026-02-26 02:34:32', 1, NULL, 1, 1, 1, 1, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `issue_files`
--

CREATE TABLE `issue_files` (
  `file_id` bigint NOT NULL,
  `issue_id` bigint NOT NULL,
  `file_name` varchar(90) NOT NULL,
  `file_type` varchar(255) NOT NULL,
  `file_size` bigint NOT NULL,
  `content_type` bigint NOT NULL,
  `original_file_name` varchar(127) DEFAULT NULL,
  `date_uploaded` datetime NOT NULL,
  `date_modified` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Relationships between issues and issue files, such as cover images.';

-- --------------------------------------------------------

--
-- Table structure for table `issue_galleys`
--

CREATE TABLE `issue_galleys` (
  `galley_id` bigint NOT NULL,
  `locale` varchar(28) DEFAULT NULL,
  `issue_id` bigint NOT NULL,
  `file_id` bigint NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `seq` double NOT NULL DEFAULT '0',
  `url_path` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Issue galleys are representations of the entire issue in a single file, such as a complete issue PDF.';

-- --------------------------------------------------------

--
-- Table structure for table `issue_galley_settings`
--

CREATE TABLE `issue_galley_settings` (
  `issue_galley_setting_id` bigint UNSIGNED NOT NULL,
  `galley_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL COMMENT '(bool|int|float|string|object)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about issue galleys, including localized content such as labels.';

-- --------------------------------------------------------

--
-- Table structure for table `issue_settings`
--

CREATE TABLE `issue_settings` (
  `issue_setting_id` bigint UNSIGNED NOT NULL,
  `issue_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about issues, including localized properties such as issue titles.';

--
-- Dumping data for table `issue_settings`
--

INSERT INTO `issue_settings` (`issue_setting_id`, `issue_id`, `locale`, `setting_name`, `setting_value`) VALUES
(1, 1, 'en', 'description', ''),
(2, 1, 'en', 'title', 'Issue 1'),
(3, 2, 'en', 'description', ''),
(4, 2, 'en', 'title', 'Issue 2');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint UNSIGNED NOT NULL,
  `reserved_at` int UNSIGNED DEFAULT NULL,
  `available_at` int UNSIGNED NOT NULL,
  `created_at` int UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='All pending or in-progress jobs.';

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` text NOT NULL,
  `options` mediumtext,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Job batches allow jobs to be collected into groups for managed processing.';

-- --------------------------------------------------------

--
-- Table structure for table `journals`
--

CREATE TABLE `journals` (
  `journal_id` bigint NOT NULL,
  `path` varchar(32) NOT NULL,
  `seq` double NOT NULL DEFAULT '0' COMMENT 'Used to order lists of journals',
  `primary_locale` varchar(28) NOT NULL,
  `enabled` smallint NOT NULL DEFAULT '1' COMMENT 'Controls whether or not the journal is considered "live" and will appear on the website. (Note that disabled journals may still be accessible, but only if the user knows the URL.)',
  `current_issue_id` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A list of all journals in the installation of OJS.';

--
-- Dumping data for table `journals`
--

INSERT INTO `journals` (`journal_id`, `path`, `seq`, `primary_locale`, `enabled`, `current_issue_id`) VALUES
(1, 'codecheck', 1, 'en', 0, 2);

-- --------------------------------------------------------

--
-- Table structure for table `journal_settings`
--

CREATE TABLE `journal_settings` (
  `journal_setting_id` bigint UNSIGNED NOT NULL,
  `journal_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about journals, including localized properties like policies.';

--
-- Dumping data for table `journal_settings`
--

INSERT INTO `journal_settings` (`journal_setting_id`, `journal_id`, `locale`, `setting_name`, `setting_value`) VALUES
(1, 1, 'en', 'acronym', 'CDJ'),
(2, 1, 'de', 'acronym', NULL),
(3, 1, 'en', 'authorGuidelines', '<p>Authors are invited to make a submission to this journal. All submissions will be assessed by an editor to determine whether they meet the aims and scope of this journal. Those considered to be a good fit will be sent for peer review before determining whether they will be accepted or rejected.</p><p>Before making a submission, authors are responsible for obtaining permission to publish any material included with the submission, such as photos, documents and datasets. All authors identified on the submission must consent to be identified as an author. Where appropriate, research should be approved by an appropriate ethics committee in accordance with the legal requirements of the study\'s country.</p><p>An editor may desk reject a submission if it does not meet minimum standards of quality. Before submitting, please ensure that the study design and research argument are structured and articulated properly. The title should be concise and the abstract should be able to stand on its own. This will increase the likelihood of reviewers agreeing to review the paper. When you\'re satisfied that your submission meets this standard, please follow the checklist below to prepare your submission.</p>'),
(4, 1, 'en', 'authorInformation', 'Interested in submitting to this journal? We recommend that you review the <a href=\"http://localhost:8888/ojs/index.php/codecheck/about\">About the Journal</a> page for the journal\'s section policies, as well as the <a href=\"http://localhost:8888/ojs/index.php/codecheck/about/submissions#authorGuidelines\">Author Guidelines</a>. Authors need to <a href=\"http://localhost:8888/ojs/index.php/codecheck/user/register\">register</a> with the journal prior to submitting or, if already registered, can simply <a href=\"http://localhost:8888/ojs/index.php/index/login\">log in</a> and begin the five-step process.'),
(5, 1, 'en', 'beginSubmissionHelp', '<p>Thank you for submitting to the CODECHECK Demo Journal. You will be asked to upload files, identify co-authors, and provide information such as the title and abstract.</p><p>Please read our <a href=\"http://localhost:8888/ojs/index.php/codecheck/about/submissions\" target=\"_blank\">Submission Guidelines</a> if you have not done so already. When filling out the forms, provide as many details as possible in order to help our editors evaluate your work.</p><p>Once you begin, you can save your submission and come back to it later. You will be able to review and correct any information before you submit.</p>'),
(6, 1, '', 'contactEmail', 'admin@example.com'),
(7, 1, '', 'contactName', 'Admin User'),
(8, 1, 'en', 'contributorsHelp', '<p>Add details for all of the contributors to this submission. Contributors added here will be sent an email confirmation of the submission, as well as a copy of all editorial decisions recorded against this submission.</p><p>If a contributor can not be contacted by email, because they must remain anonymous or do not have an email account, please do not enter a fake email address. You can add information about this contributor in a message to the editor at a later step in the submission process.</p>'),
(9, 1, '', 'country', 'DE'),
(10, 1, '', 'defaultReviewMode', '2'),
(13, 1, 'en', 'detailsHelp', '<p>Please provide the following details to help us manage your submission in our system.</p>'),
(14, 1, '', 'copySubmissionAckPrimaryContact', '0'),
(15, 1, '', 'copySubmissionAckAddress', ''),
(16, 1, '', 'emailSignature', '<br><br>—<br><p>This is an automated message from <a href=\"http://localhost:8888/ojs/index.php/codecheck\">CODECHECK Demo Journal</a>.</p>'),
(17, 1, '', 'enableDois', '1'),
(18, 1, '', 'doiSuffixType', 'default'),
(19, 1, '', 'registrationAgency', ''),
(20, 1, '', 'disableSubmissions', '0'),
(21, 1, '', 'editorialStatsEmail', '1'),
(22, 1, 'en', 'forTheEditorsHelp', '<p>Please provide the following details in order to help our editorial team manage your submission.</p><p>When entering metadata, provide entries that you think would be most helpful to the person managing your submission. This information can be changed before publication.</p>'),
(23, 1, '', 'itemsPerPage', '25'),
(24, 1, '', 'keywords', 'request'),
(25, 1, 'en', 'librarianInformation', 'We encourage research librarians to list this journal among their library\'s electronic journal holdings. As well, it may be worth noting that this journal\'s open source publishing system is suitable for libraries to host for their faculty members to use with journals they are involved in editing (see <a href=\"https://pkp.sfu.ca/ojs\">Open Journal Systems</a>).'),
(26, 1, 'en', 'name', 'CODECHECK Demo Journal'),
(27, 1, 'de', 'name', NULL),
(28, 1, '', 'notifyAllAuthors', '1'),
(29, 1, '', 'numPageLinks', '10'),
(30, 1, '', 'numWeeksPerResponse', '4'),
(31, 1, '', 'numWeeksPerReview', '4'),
(32, 1, '', 'numReviewsPerSubmission', '0'),
(33, 1, 'en', 'openAccessPolicy', 'This journal provides immediate open access to its content on the principle that making research freely available to the public supports a greater global exchange of knowledge.'),
(34, 1, '', 'orcidCity', ''),
(35, 1, '', 'orcidClientId', ''),
(36, 1, '', 'orcidClientSecret', ''),
(37, 1, '', 'orcidEnabled', '0'),
(38, 1, '', 'orcidLogLevel', 'ERROR'),
(39, 1, '', 'orcidSendMailToAuthorsOnPublication', '0'),
(40, 1, 'en', 'privacyStatement', '<p>The names and email addresses entered in this journal site will be used exclusively for the stated purposes of this journal and will not be made available for any other purpose or to any other party.</p>'),
(41, 1, 'en', 'readerInformation', 'We encourage readers to sign up for the publishing notification service for this journal. Use the <a href=\"http://localhost:8888/ojs/index.php/codecheck/user/register\">Register</a> link at the top of the home page for the journal. This registration will result in the reader receiving the Table of Contents by email for each new issue of the journal. This list also allows the journal to claim a certain level of support or readership. See the journal\'s <a href=\"http://localhost:8888/ojs/index.php/codecheck/about/submissions#privacyStatement\">Privacy Statement</a>, which assures readers that their name and email address will not be used for other purposes.'),
(42, 1, 'en', 'reviewHelp', '<p>Review the information you have entered before you complete your submission. You can change any of the details displayed here by clicking the edit button at the top of each section.</p><p>Once you complete your submission, a member of our editorial team will be assigned to review it. Please ensure the details you have entered here are as accurate as possible.</p>'),
(43, 1, '', 'submissionAcknowledgement', 'allAuthors'),
(44, 1, 'en', 'submissionChecklist', '<p>All submissions must meet the following requirements.</p><ul><li>This submission meets the requirements outlined in the <a href=\"http://localhost:8888/ojs/index.php/codecheck/about/submissions\">Author Guidelines</a>.</li><li>This submission has not been previously published, nor is it before another journal for consideration.</li><li>All references have been checked for accuracy and completeness.</li><li>All tables and figures have been numbered and labeled.</li><li>Permission has been obtained to publish all photos, datasets and other material provided with this submission.</li></ul>'),
(45, 1, '', 'submitWithCategories', '0'),
(46, 1, '', 'supportedAddedSubmissionLocales', '[\"en\"]'),
(47, 1, '', 'supportedDefaultSubmissionLocale', 'en'),
(48, 1, '', 'supportedFormLocales', '[\"en\"]'),
(49, 1, '', 'supportedLocales', '[\"en\"]'),
(50, 1, '', 'supportedSubmissionLocales', '[\"en\"]'),
(51, 1, '', 'supportedSubmissionMetadataLocales', '[\"en\"]'),
(52, 1, '', 'themePluginPath', 'default'),
(53, 1, 'en', 'uploadFilesHelp', '<p>Provide any files our editorial team may need to evaluate your submission. In addition to the main work, you may wish to submit data sets, conflict of interest statements, or other supplementary files if these will be helpful for our editors.</p>'),
(54, 1, '', 'enableGeoUsageStats', 'disabled'),
(55, 1, '', 'enableInstitutionUsageStats', '0'),
(56, 1, '', 'isSushiApiPublic', '1'),
(59, 1, 'en', 'clockssLicense', 'This journal utilizes the CLOCKSS system to create a distributed archiving system among participating libraries and permits those libraries to create permanent archives of the journal for purposes of preservation and restoration. <a href=\"https://clockss.org\">More...</a>'),
(60, 1, '', 'copyrightYearBasis', 'issue'),
(61, 1, '', 'enabledDoiTypes', '[\"publication\"]'),
(62, 1, '', 'doiCreationTime', 'copyEditCreationTime'),
(63, 1, '', 'enableOai', '1'),
(64, 1, 'en', 'lockssLicense', 'This journal utilizes the LOCKSS system to create a distributed archiving system among participating libraries and permits those libraries to create permanent archives of the journal for purposes of preservation and restoration. <a href=\"https://www.lockss.org\">More...</a>'),
(65, 1, '', 'membershipFee', '0'),
(66, 1, '', 'publicationFee', '0'),
(67, 1, '', 'purchaseArticleFee', '0'),
(68, 1, '', 'doiVersioning', '0'),
(69, 1, 'en', 'reviewerSuggestionsHelp', '<p>When submitting, you have the option to suggest several potential reviewers. This can help streamline the review process and provide valueable input for the editorial team. Please choose reviewers who are expert in your field and have no conflict of interest with your work. This feature aims to enhance the review process and support a more efficient experience for both authors and editorial team.</p>');

-- --------------------------------------------------------

--
-- Table structure for table `library_files`
--

CREATE TABLE `library_files` (
  `file_id` bigint NOT NULL,
  `context_id` bigint NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_file_name` varchar(255) NOT NULL,
  `file_type` varchar(255) NOT NULL,
  `file_size` bigint NOT NULL,
  `type` smallint NOT NULL,
  `date_uploaded` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  `submission_id` bigint DEFAULT NULL,
  `public_access` smallint DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Library files can be associated with the context (press/server/journal) or with individual submissions, and are typically forms, agreements, and other administrative documents that are not part of the scholarly content.';

-- --------------------------------------------------------

--
-- Table structure for table `library_file_settings`
--

CREATE TABLE `library_file_settings` (
  `library_file_setting_id` bigint UNSIGNED NOT NULL,
  `file_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL COMMENT '(bool|int|float|string|object|date)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about library files, including localized content such as names.';

-- --------------------------------------------------------

--
-- Table structure for table `metrics_context`
--

CREATE TABLE `metrics_context` (
  `metrics_context_id` bigint UNSIGNED NOT NULL,
  `load_id` varchar(50) NOT NULL,
  `context_id` bigint NOT NULL,
  `date` date NOT NULL,
  `metric` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Daily statistics for views of the homepage.';

-- --------------------------------------------------------

--
-- Table structure for table `metrics_counter_submission_daily`
--

CREATE TABLE `metrics_counter_submission_daily` (
  `metrics_counter_submission_daily_id` bigint UNSIGNED NOT NULL,
  `load_id` varchar(50) NOT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `date` date NOT NULL,
  `metric_investigations` int NOT NULL,
  `metric_investigations_unique` int NOT NULL,
  `metric_requests` int NOT NULL,
  `metric_requests_unique` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Daily statistics matching the COUNTER R5 protocol for views and downloads of published submissions and galleys.';

-- --------------------------------------------------------

--
-- Table structure for table `metrics_counter_submission_institution_daily`
--

CREATE TABLE `metrics_counter_submission_institution_daily` (
  `metrics_counter_submission_institution_daily_id` bigint UNSIGNED NOT NULL,
  `load_id` varchar(50) NOT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `institution_id` bigint NOT NULL,
  `date` date NOT NULL,
  `metric_investigations` int NOT NULL,
  `metric_investigations_unique` int NOT NULL,
  `metric_requests` int NOT NULL,
  `metric_requests_unique` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Daily statistics matching the COUNTER R5 protocol for views and downloads from institutions.';

-- --------------------------------------------------------

--
-- Table structure for table `metrics_counter_submission_institution_monthly`
--

CREATE TABLE `metrics_counter_submission_institution_monthly` (
  `metrics_counter_submission_institution_monthly_id` bigint UNSIGNED NOT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `institution_id` bigint NOT NULL,
  `month` int NOT NULL,
  `metric_investigations` int NOT NULL,
  `metric_investigations_unique` int NOT NULL,
  `metric_requests` int NOT NULL,
  `metric_requests_unique` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Monthly statistics matching the COUNTER R5 protocol for views and downloads from institutions.';

-- --------------------------------------------------------

--
-- Table structure for table `metrics_counter_submission_monthly`
--

CREATE TABLE `metrics_counter_submission_monthly` (
  `metrics_counter_submission_monthly_id` bigint UNSIGNED NOT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `month` int NOT NULL,
  `metric_investigations` int NOT NULL,
  `metric_investigations_unique` int NOT NULL,
  `metric_requests` int NOT NULL,
  `metric_requests_unique` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Monthly statistics matching the COUNTER R5 protocol for views and downloads of published submissions and galleys.';

-- --------------------------------------------------------

--
-- Table structure for table `metrics_issue`
--

CREATE TABLE `metrics_issue` (
  `metrics_issue_id` bigint UNSIGNED NOT NULL,
  `load_id` varchar(50) NOT NULL,
  `context_id` bigint NOT NULL,
  `issue_id` bigint NOT NULL,
  `issue_galley_id` bigint DEFAULT NULL,
  `date` date NOT NULL,
  `metric` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Daily statistics for views and downloads of published issues.';

-- --------------------------------------------------------

--
-- Table structure for table `metrics_submission`
--

CREATE TABLE `metrics_submission` (
  `metrics_submission_id` bigint UNSIGNED NOT NULL,
  `load_id` varchar(50) NOT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `representation_id` bigint DEFAULT NULL,
  `submission_file_id` bigint UNSIGNED DEFAULT NULL,
  `file_type` bigint DEFAULT NULL,
  `assoc_type` bigint NOT NULL,
  `date` date NOT NULL,
  `metric` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Daily statistics for views and downloads of published submissions and galleys.';

-- --------------------------------------------------------

--
-- Table structure for table `metrics_submission_geo_daily`
--

CREATE TABLE `metrics_submission_geo_daily` (
  `metrics_submission_geo_daily_id` bigint UNSIGNED NOT NULL,
  `load_id` varchar(50) NOT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `country` varchar(2) NOT NULL DEFAULT '',
  `region` varchar(3) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  `date` date NOT NULL,
  `metric` int NOT NULL,
  `metric_unique` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Daily statistics by country, region and city for views and downloads of published submissions and galleys.';

-- --------------------------------------------------------

--
-- Table structure for table `metrics_submission_geo_monthly`
--

CREATE TABLE `metrics_submission_geo_monthly` (
  `metrics_submission_geo_monthly_id` bigint UNSIGNED NOT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `country` varchar(2) NOT NULL DEFAULT '',
  `region` varchar(3) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  `month` int NOT NULL,
  `metric` int NOT NULL,
  `metric_unique` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Monthly statistics by country, region and city for views and downloads of published submissions and galleys.';

-- --------------------------------------------------------

--
-- Table structure for table `navigation_menus`
--

CREATE TABLE `navigation_menus` (
  `navigation_menu_id` bigint NOT NULL,
  `context_id` bigint DEFAULT NULL,
  `area_name` varchar(255) DEFAULT '',
  `title` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Navigation menus on the website are installed with the software as a default set, and can be customized.';

--
-- Dumping data for table `navigation_menus`
--

INSERT INTO `navigation_menus` (`navigation_menu_id`, `context_id`, `area_name`, `title`) VALUES
(1, NULL, 'user', 'User Navigation Menu'),
(2, 1, 'user', 'User Navigation Menu'),
(3, 1, 'primary', 'Primary Navigation Menu');

-- --------------------------------------------------------

--
-- Table structure for table `navigation_menu_items`
--

CREATE TABLE `navigation_menu_items` (
  `navigation_menu_item_id` bigint NOT NULL,
  `context_id` bigint DEFAULT NULL,
  `path` varchar(255) DEFAULT '',
  `type` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Navigation menu items are single elements within a navigation menu.';

--
-- Dumping data for table `navigation_menu_items`
--

INSERT INTO `navigation_menu_items` (`navigation_menu_item_id`, `context_id`, `path`, `type`) VALUES
(1, NULL, NULL, 'NMI_TYPE_USER_REGISTER'),
(2, NULL, NULL, 'NMI_TYPE_USER_LOGIN'),
(3, NULL, NULL, 'NMI_TYPE_USER_DASHBOARD'),
(4, NULL, NULL, 'NMI_TYPE_USER_DASHBOARD'),
(5, NULL, NULL, 'NMI_TYPE_USER_PROFILE'),
(6, NULL, NULL, 'NMI_TYPE_ADMINISTRATION'),
(7, NULL, NULL, 'NMI_TYPE_USER_LOGOUT'),
(8, 1, NULL, 'NMI_TYPE_USER_REGISTER'),
(9, 1, NULL, 'NMI_TYPE_USER_LOGIN'),
(10, 1, NULL, 'NMI_TYPE_USER_DASHBOARD'),
(11, 1, NULL, 'NMI_TYPE_USER_DASHBOARD'),
(12, 1, NULL, 'NMI_TYPE_USER_PROFILE'),
(13, 1, NULL, 'NMI_TYPE_ADMINISTRATION'),
(14, 1, NULL, 'NMI_TYPE_USER_LOGOUT'),
(15, 1, NULL, 'NMI_TYPE_CURRENT'),
(16, 1, NULL, 'NMI_TYPE_ARCHIVES'),
(17, 1, NULL, 'NMI_TYPE_ANNOUNCEMENTS'),
(18, 1, NULL, 'NMI_TYPE_ABOUT'),
(19, 1, NULL, 'NMI_TYPE_ABOUT'),
(20, 1, NULL, 'NMI_TYPE_SUBMISSIONS'),
(21, 1, NULL, 'NMI_TYPE_MASTHEAD'),
(22, 1, NULL, 'NMI_TYPE_PRIVACY'),
(23, 1, NULL, 'NMI_TYPE_CONTACT'),
(24, 1, NULL, 'NMI_TYPE_SEARCH');

-- --------------------------------------------------------

--
-- Table structure for table `navigation_menu_item_assignments`
--

CREATE TABLE `navigation_menu_item_assignments` (
  `navigation_menu_item_assignment_id` bigint NOT NULL,
  `navigation_menu_id` bigint NOT NULL,
  `navigation_menu_item_id` bigint NOT NULL,
  `parent_id` bigint DEFAULT NULL,
  `seq` bigint DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Links navigation menu items to navigation menus.';

--
-- Dumping data for table `navigation_menu_item_assignments`
--

INSERT INTO `navigation_menu_item_assignments` (`navigation_menu_item_assignment_id`, `navigation_menu_id`, `navigation_menu_item_id`, `parent_id`, `seq`) VALUES
(1, 1, 1, NULL, 0),
(2, 1, 2, NULL, 1),
(3, 1, 3, NULL, 2),
(4, 1, 4, 3, 0),
(5, 1, 5, 3, 1),
(6, 1, 6, 3, 2),
(7, 1, 7, 3, 3),
(8, 2, 8, NULL, 0),
(9, 2, 9, NULL, 1),
(10, 2, 10, NULL, 2),
(11, 2, 11, 10, 0),
(12, 2, 12, 10, 1),
(13, 2, 13, 10, 2),
(14, 2, 14, 10, 3),
(15, 3, 15, NULL, 0),
(16, 3, 16, NULL, 1),
(17, 3, 17, NULL, 2),
(18, 3, 18, NULL, 3),
(19, 3, 19, 18, 0),
(20, 3, 20, 18, 1),
(21, 3, 21, 18, 2),
(22, 3, 22, 18, 3),
(23, 3, 23, 18, 4);

-- --------------------------------------------------------

--
-- Table structure for table `navigation_menu_item_assignment_settings`
--

CREATE TABLE `navigation_menu_item_assignment_settings` (
  `navigation_menu_item_assignment_setting_id` bigint UNSIGNED NOT NULL,
  `navigation_menu_item_assignment_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about navigation menu item assignments to navigation menus, including localized content.';

-- --------------------------------------------------------

--
-- Table structure for table `navigation_menu_item_settings`
--

CREATE TABLE `navigation_menu_item_settings` (
  `navigation_menu_item_setting_id` bigint UNSIGNED NOT NULL,
  `navigation_menu_item_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` longtext,
  `setting_type` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about navigation menu items, including localized content such as names.';

--
-- Dumping data for table `navigation_menu_item_settings`
--

INSERT INTO `navigation_menu_item_settings` (`navigation_menu_item_setting_id`, `navigation_menu_item_id`, `locale`, `setting_name`, `setting_value`, `setting_type`) VALUES
(1, 1, '', 'titleLocaleKey', 'navigation.register', 'string'),
(2, 2, '', 'titleLocaleKey', 'navigation.login', 'string'),
(3, 3, '', 'titleLocaleKey', '{$loggedInUsername}', 'string'),
(4, 4, '', 'titleLocaleKey', 'navigation.dashboard', 'string'),
(5, 5, '', 'titleLocaleKey', 'common.viewProfile', 'string'),
(6, 6, '', 'titleLocaleKey', 'navigation.admin', 'string'),
(7, 7, '', 'titleLocaleKey', 'user.logOut', 'string'),
(8, 8, '', 'titleLocaleKey', 'navigation.register', 'string'),
(9, 9, '', 'titleLocaleKey', 'navigation.login', 'string'),
(10, 10, '', 'titleLocaleKey', '{$loggedInUsername}', 'string'),
(11, 11, '', 'titleLocaleKey', 'navigation.dashboard', 'string'),
(12, 12, '', 'titleLocaleKey', 'common.viewProfile', 'string'),
(13, 13, '', 'titleLocaleKey', 'navigation.admin', 'string'),
(14, 14, '', 'titleLocaleKey', 'user.logOut', 'string'),
(15, 15, '', 'titleLocaleKey', 'navigation.current', 'string'),
(16, 16, '', 'titleLocaleKey', 'navigation.archives', 'string'),
(17, 17, '', 'titleLocaleKey', 'manager.announcements', 'string'),
(18, 18, '', 'titleLocaleKey', 'navigation.about', 'string'),
(19, 19, '', 'titleLocaleKey', 'about.aboutContext', 'string'),
(20, 20, '', 'titleLocaleKey', 'about.submissions', 'string'),
(21, 21, '', 'titleLocaleKey', 'common.editorialMasthead', 'string'),
(22, 22, '', 'titleLocaleKey', 'manager.setup.privacyStatement', 'string'),
(23, 23, '', 'titleLocaleKey', 'about.contact', 'string'),
(24, 24, '', 'titleLocaleKey', 'common.search', 'string');

-- --------------------------------------------------------

--
-- Table structure for table `notes`
--

CREATE TABLE `notes` (
  `note_id` bigint NOT NULL,
  `assoc_type` bigint NOT NULL,
  `assoc_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` datetime DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `contents` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Notes allow users to annotate associated entities, such as submissions.';

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint NOT NULL,
  `context_id` bigint DEFAULT NULL,
  `user_id` bigint DEFAULT NULL,
  `level` bigint NOT NULL,
  `type` bigint NOT NULL,
  `date_created` datetime NOT NULL,
  `date_read` datetime DEFAULT NULL,
  `assoc_type` bigint DEFAULT NULL,
  `assoc_id` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='User notifications created during certain operations.';

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `context_id`, `user_id`, `level`, `type`, `date_created`, `date_read`, `assoc_type`, `assoc_id`) VALUES
(16, 1, NULL, 3, 16777220, '2026-02-26 00:55:09', NULL, 1048585, 2),
(17, 1, NULL, 3, 16777222, '2026-02-26 00:55:09', NULL, 1048585, 2),
(18, 1, NULL, 3, 16777223, '2026-02-26 00:55:09', NULL, 1048585, 2),
(19, 1, NULL, 3, 16777224, '2026-02-26 00:55:09', NULL, 1048585, 2),
(20, 1, 1, 3, 16777247, '2026-02-26 00:55:09', NULL, 1048585, 2),
(21, 1, 5, 3, 16777247, '2026-02-26 00:55:10', NULL, 1048585, 2),
(22, 1, NULL, 2, 16777243, '2026-02-26 00:55:10', NULL, 1048585, 2),
(23, 1, NULL, 2, 16777245, '2026-02-26 00:55:10', NULL, 1048585, 2),
(24, 1, NULL, 3, 16777220, '2026-02-26 01:08:10', NULL, 1048585, 3),
(25, 1, NULL, 3, 16777222, '2026-02-26 01:08:10', NULL, 1048585, 3),
(26, 1, NULL, 3, 16777223, '2026-02-26 01:08:10', NULL, 1048585, 3),
(27, 1, NULL, 3, 16777224, '2026-02-26 01:08:10', NULL, 1048585, 3),
(28, 1, 1, 3, 16777247, '2026-02-26 01:08:10', NULL, 1048585, 3),
(29, 1, 5, 3, 16777247, '2026-02-26 01:08:10', NULL, 1048585, 3),
(30, 1, NULL, 2, 16777243, '2026-02-26 01:08:10', NULL, 1048585, 3),
(31, 1, NULL, 2, 16777245, '2026-02-26 01:08:10', NULL, 1048585, 3),
(32, 1, NULL, 3, 16777220, '2026-02-26 01:15:55', NULL, 1048585, 4),
(33, 1, NULL, 3, 16777222, '2026-02-26 01:15:55', NULL, 1048585, 4),
(34, 1, NULL, 3, 16777223, '2026-02-26 01:15:55', NULL, 1048585, 4),
(35, 1, NULL, 3, 16777224, '2026-02-26 01:15:55', NULL, 1048585, 4),
(36, 1, 1, 3, 16777247, '2026-02-26 01:15:55', NULL, 1048585, 4),
(37, 1, 5, 3, 16777247, '2026-02-26 01:15:55', NULL, 1048585, 4),
(38, 1, NULL, 2, 16777243, '2026-02-26 01:15:55', NULL, 1048585, 4),
(39, 1, NULL, 2, 16777245, '2026-02-26 01:15:55', NULL, 1048585, 4),
(40, 1, NULL, 3, 16777220, '2026-02-26 01:24:39', NULL, 1048585, 5),
(41, 1, NULL, 3, 16777222, '2026-02-26 01:24:39', NULL, 1048585, 5),
(42, 1, NULL, 3, 16777223, '2026-02-26 01:24:39', NULL, 1048585, 5),
(43, 1, NULL, 3, 16777224, '2026-02-26 01:24:39', NULL, 1048585, 5),
(44, 1, 1, 3, 16777247, '2026-02-26 01:24:39', NULL, 1048585, 5),
(45, 1, 5, 3, 16777247, '2026-02-26 01:24:40', NULL, 1048585, 5),
(46, 1, NULL, 2, 16777243, '2026-02-26 01:24:40', NULL, 1048585, 5),
(47, 1, NULL, 2, 16777245, '2026-02-26 01:24:40', NULL, 1048585, 5),
(48, 1, NULL, 3, 16777220, '2026-02-26 01:48:06', NULL, 1048585, 7),
(49, 1, NULL, 3, 16777222, '2026-02-26 01:48:06', NULL, 1048585, 7),
(50, 1, NULL, 3, 16777223, '2026-02-26 01:48:06', NULL, 1048585, 7),
(51, 1, NULL, 3, 16777224, '2026-02-26 01:48:06', NULL, 1048585, 7),
(52, 1, 1, 3, 16777247, '2026-02-26 01:48:06', NULL, 1048585, 7),
(53, 1, 5, 3, 16777247, '2026-02-26 01:48:06', NULL, 1048585, 7),
(54, 1, NULL, 3, 16777220, '2026-02-26 02:00:07', NULL, 1048585, 8),
(55, 1, NULL, 3, 16777222, '2026-02-26 02:00:07', NULL, 1048585, 8),
(56, 1, NULL, 3, 16777223, '2026-02-26 02:00:07', NULL, 1048585, 8),
(57, 1, NULL, 3, 16777224, '2026-02-26 02:00:07', NULL, 1048585, 8),
(58, 1, 1, 3, 16777247, '2026-02-26 02:00:07', NULL, 1048585, 8),
(59, 1, 5, 3, 16777247, '2026-02-26 02:00:07', NULL, 1048585, 8),
(60, 1, NULL, 2, 16777243, '2026-02-26 02:00:07', NULL, 1048585, 8),
(61, 1, NULL, 2, 16777245, '2026-02-26 02:00:07', NULL, 1048585, 8),
(62, 1, NULL, 3, 16777220, '2026-02-26 02:11:20', NULL, 1048585, 9),
(63, 1, NULL, 3, 16777222, '2026-02-26 02:11:20', NULL, 1048585, 9),
(64, 1, NULL, 3, 16777223, '2026-02-26 02:11:20', NULL, 1048585, 9),
(65, 1, NULL, 3, 16777224, '2026-02-26 02:11:20', NULL, 1048585, 9),
(66, 1, 1, 3, 16777247, '2026-02-26 02:11:20', '2026-02-26 14:02:53', 1048585, 9),
(67, 1, 5, 3, 16777247, '2026-02-26 02:11:21', NULL, 1048585, 9),
(68, 1, NULL, 2, 16777243, '2026-02-26 02:11:21', NULL, 1048585, 9),
(69, 1, NULL, 2, 16777245, '2026-02-26 02:11:21', NULL, 1048585, 9),
(70, 1, NULL, 3, 16777220, '2026-02-26 02:18:23', NULL, 1048585, 10),
(71, 1, NULL, 3, 16777222, '2026-02-26 02:18:23', NULL, 1048585, 10),
(72, 1, NULL, 3, 16777223, '2026-02-26 02:18:23', NULL, 1048585, 10),
(73, 1, NULL, 3, 16777224, '2026-02-26 02:18:23', NULL, 1048585, 10),
(74, 1, 1, 3, 16777247, '2026-02-26 02:18:23', '2026-02-26 14:02:53', 1048585, 10),
(75, 1, 5, 3, 16777247, '2026-02-26 02:18:24', NULL, 1048585, 10),
(76, 1, NULL, 2, 16777243, '2026-02-26 02:18:24', NULL, 1048585, 10),
(77, 1, NULL, 2, 16777245, '2026-02-26 02:18:24', NULL, 1048585, 10),
(78, 1, NULL, 2, 16777236, '2026-02-26 02:31:37', NULL, 523, 1),
(79, 1, 3, 2, 16777231, '2026-02-26 02:31:37', NULL, 1048585, 8),
(80, 1, NULL, 2, 16777236, '2026-02-26 02:32:11', NULL, 523, 2),
(81, 1, 2, 2, 16777231, '2026-02-26 02:32:11', NULL, 1048585, 9);

-- --------------------------------------------------------

--
-- Table structure for table `notification_settings`
--

CREATE TABLE `notification_settings` (
  `notification_setting_id` bigint UNSIGNED NOT NULL,
  `notification_id` bigint NOT NULL,
  `locale` varchar(28) DEFAULT NULL,
  `setting_name` varchar(64) NOT NULL,
  `setting_value` mediumtext NOT NULL,
  `setting_type` varchar(6) NOT NULL COMMENT '(bool|int|float|string|object)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about notifications, including localized properties.';

-- --------------------------------------------------------

--
-- Table structure for table `notification_subscription_settings`
--

CREATE TABLE `notification_subscription_settings` (
  `setting_id` bigint NOT NULL,
  `setting_name` varchar(64) NOT NULL,
  `setting_value` mediumtext NOT NULL,
  `user_id` bigint NOT NULL,
  `context_id` bigint DEFAULT NULL,
  `setting_type` varchar(6) NOT NULL COMMENT '(bool|int|float|string|object)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Which email notifications a user has chosen to unsubscribe from.';

-- --------------------------------------------------------

--
-- Table structure for table `oai_resumption_tokens`
--

CREATE TABLE `oai_resumption_tokens` (
  `oai_resumption_token_id` bigint UNSIGNED NOT NULL,
  `token` varchar(32) NOT NULL,
  `expire` bigint NOT NULL,
  `record_offset` int NOT NULL,
  `params` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='OAI resumption tokens are used to allow for pagination of large result sets into manageable pieces.';

-- --------------------------------------------------------

--
-- Table structure for table `plugin_settings`
--

CREATE TABLE `plugin_settings` (
  `plugin_setting_id` bigint UNSIGNED NOT NULL,
  `plugin_name` varchar(80) NOT NULL,
  `context_id` bigint DEFAULT NULL,
  `setting_name` varchar(80) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL COMMENT '(bool|int|float|string|object)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about plugins, including localized properties. This table is frequently used to store plugin-specific configuration.';

--
-- Dumping data for table `plugin_settings`
--

INSERT INTO `plugin_settings` (`plugin_setting_id`, `plugin_name`, `context_id`, `setting_name`, `setting_value`, `setting_type`) VALUES
(1, 'defaultthemeplugin', NULL, 'enabled', '1', 'bool'),
(2, 'usageeventplugin', NULL, 'enabled', '1', 'bool'),
(3, 'tinymceplugin', NULL, 'enabled', '1', 'bool'),
(4, 'developedbyblockplugin', NULL, 'enabled', '0', 'bool'),
(5, 'developedbyblockplugin', NULL, 'seq', '0', 'int'),
(6, 'languagetoggleblockplugin', NULL, 'enabled', '1', 'bool'),
(7, 'languagetoggleblockplugin', NULL, 'seq', '4', 'int'),
(8, 'codecheckplugin', NULL, 'enabled', '1', 'bool'),
(9, 'tinymceplugin', 1, 'enabled', '1', 'bool'),
(10, 'defaultthemeplugin', 1, 'enabled', '1', 'bool'),
(11, 'developedbyblockplugin', 1, 'enabled', '0', 'bool'),
(12, 'developedbyblockplugin', 1, 'seq', '0', 'int'),
(13, 'informationblockplugin', 1, 'enabled', '1', 'bool'),
(14, 'informationblockplugin', 1, 'seq', '7', 'int'),
(15, 'subscriptionblockplugin', 1, 'enabled', '1', 'bool'),
(16, 'subscriptionblockplugin', 1, 'seq', '2', 'int'),
(17, 'languagetoggleblockplugin', 1, 'enabled', '1', 'bool'),
(18, 'languagetoggleblockplugin', 1, 'seq', '4', 'int'),
(19, 'lensgalleyplugin', 1, 'enabled', '1', 'bool'),
(20, 'googlescholarplugin', 1, 'enabled', '1', 'bool'),
(21, 'dublincoremetaplugin', 1, 'enabled', '1', 'bool'),
(22, 'webfeedplugin', 1, 'enabled', '1', 'bool'),
(23, 'webfeedplugin', 1, 'displayPage', 'homepage', 'string'),
(24, 'webfeedplugin', 1, 'displayItems', '1', 'bool'),
(25, 'webfeedplugin', 1, 'recentItems', '30', 'int'),
(26, 'webfeedplugin', 1, 'includeIdentifiers', '0', 'bool'),
(27, 'htmlarticlegalleyplugin', 1, 'enabled', '1', 'bool'),
(28, 'pdfjsviewerplugin', 1, 'enabled', '1', 'bool'),
(29, 'jatstemplateplugin', 1, 'enabled', '1', 'bool'),
(30, 'codecheckplugin', 1, 'enabled', '1', 'bool');

-- --------------------------------------------------------

--
-- Table structure for table `publications`
--

CREATE TABLE `publications` (
  `publication_id` bigint NOT NULL,
  `access_status` bigint DEFAULT '0',
  `date_published` date DEFAULT NULL,
  `last_modified` datetime DEFAULT NULL,
  `primary_contact_id` bigint DEFAULT NULL,
  `section_id` bigint DEFAULT NULL,
  `seq` double NOT NULL DEFAULT '0',
  `submission_id` bigint NOT NULL,
  `status` smallint NOT NULL DEFAULT '1',
  `url_path` varchar(64) DEFAULT NULL,
  `version` bigint DEFAULT NULL,
  `doi_id` bigint DEFAULT NULL,
  `issue_id` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Each publication is one version of a submission.';

--
-- Dumping data for table `publications`
--

INSERT INTO `publications` (`publication_id`, `access_status`, `date_published`, `last_modified`, `primary_contact_id`, `section_id`, `seq`, `submission_id`, `status`, `url_path`, `version`, `doi_id`, `issue_id`) VALUES
(2, 0, '2026-02-26', '2026-02-26 02:34:25', 4, 1, 0, 2, 3, NULL, 1, NULL, 1),
(3, 0, '2026-02-26', '2026-02-26 02:34:25', 7, 1, 0, 3, 3, NULL, 1, NULL, 1),
(4, 0, '2026-02-26', '2026-02-26 02:34:25', 11, 1, 0, 4, 3, NULL, 1, NULL, 1),
(5, 0, '2026-02-26', '2026-02-26 02:34:32', 13, 1, 0, 5, 3, NULL, 1, NULL, 2),
(7, 0, '2026-02-26', '2026-02-26 02:34:32', 15, 1, 0, 7, 3, NULL, 1, NULL, 2),
(8, 0, NULL, '2026-02-26 01:59:52', 18, 1, 0, 8, 1, NULL, 1, NULL, NULL),
(9, 0, NULL, '2026-02-26 02:11:13', 22, 1, 0, 9, 1, NULL, 1, NULL, NULL),
(10, 0, NULL, '2026-02-26 02:18:15', 28, 1, 0, 10, 1, NULL, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `publication_categories`
--

CREATE TABLE `publication_categories` (
  `publication_category_id` bigint UNSIGNED NOT NULL,
  `publication_id` bigint NOT NULL,
  `category_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Associates publications (and thus submissions) with categories.';

-- --------------------------------------------------------

--
-- Table structure for table `publication_galleys`
--

CREATE TABLE `publication_galleys` (
  `galley_id` bigint NOT NULL,
  `locale` varchar(28) DEFAULT NULL,
  `publication_id` bigint NOT NULL,
  `label` varchar(255) DEFAULT NULL,
  `submission_file_id` bigint UNSIGNED DEFAULT NULL,
  `seq` double NOT NULL DEFAULT '0',
  `remote_url` varchar(2047) DEFAULT NULL,
  `is_approved` smallint NOT NULL DEFAULT '0',
  `url_path` varchar(64) DEFAULT NULL,
  `doi_id` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Publication galleys are representations of a publication in a specific format, e.g. a PDF.';

-- --------------------------------------------------------

--
-- Table structure for table `publication_galley_settings`
--

CREATE TABLE `publication_galley_settings` (
  `publication_galley_setting_id` bigint UNSIGNED NOT NULL,
  `galley_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about publication galleys, including localized content such as labels.';

-- --------------------------------------------------------

--
-- Table structure for table `publication_settings`
--

CREATE TABLE `publication_settings` (
  `publication_setting_id` bigint UNSIGNED NOT NULL,
  `publication_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about publications, including localized properties such as the title and abstract.';

--
-- Dumping data for table `publication_settings`
--

INSERT INTO `publication_settings` (`publication_setting_id`, `publication_id`, `locale`, `setting_name`, `setting_value`) VALUES
(3, 2, 'en', 'title', 'The principal components of natural images'),
(4, 2, 'en', 'abstract', '<p>A neural net was used to analyse samples of natural images and text. For the natural images, components resemble derivatives of Gaussian operators, similar to those found in visual cortex and inferred from psychophysics. While the results from natural images do not depend on scale, those from text images are highly scale dependent. Convolution of one of the text components with an original image shows that it is sensitive to inter-word gaps.</p>'),
(5, 2, '', 'codeRepository', 'https://github.com/IainDaviesMaths/Reproduction-Hancock'),
(6, 2, '', 'manifestFiles', 'Figure2.png - manuscript Figure 2\nFigure3.png - manuscript Figure 3\nFigure4.png - manuscript Figure 4\nFigure5.png - manuscript Figure 5\nFigure6.png - manuscript Figure 6\nFigure7.png - manuscript Figure 7\nFigure8.png - manuscript Figure 8'),
(7, 2, '', 'dataAvailabilityStatement', 'Code available at https://github.com/IainDaviesMaths/Reproduction-Hancock'),
(8, 3, 'en', 'title', 'ShinyLearner: A containerized benchmarking tool for machine-learning classification of tabular data.'),
(9, 3, 'en', 'abstract', '<section class=\"abstract\" aria-label=\"Main abstract\">\n<div class=\" sec\">\n<div class=\"title\"><strong>Background</strong></div>\n<p class=\"chapter-para\">Classification algorithms assign observations to groups based on patterns in data. The machine-learning community have developed myriad classification algorithms, which are used in diverse life science research domains. Algorithm choice can affect classification accuracy dramatically, so it is crucial that researchers optimize the choice of which algorithm(s) to apply in a given research domain on the basis of empirical evidence. In benchmark studies, multiple algorithms are applied to multiple datasets, and the researcher examines overall trends. In addition, the researcher may evaluate multiple hyperparameter combinations for each algorithm and use feature selection to reduce data dimensionality. Although software implementations of classification algorithms are widely available, robust benchmark comparisons are difficult to perform when researchers wish to compare algorithms that span multiple software packages. Programming interfaces, data formats, and evaluation procedures differ across software packages; and dependency conflicts may arise during installation.</p>\n</div>\n<div class=\" sec\">\n<div class=\"title\"><strong>Findings</strong></div>\n<p class=\"chapter-para\">To address these challenges, we created ShinyLearner, an open-source project for integrating machine-learning packages into software containers. ShinyLearner provides a uniform interface for performing classification, irrespective of the library that implements each algorithm, thus facilitating benchmark comparisons. In addition, ShinyLearner enables researchers to optimize hyperparameters and select features via nested cross-validation; it tracks all nested operations and generates output files that make these steps transparent. ShinyLearner includes a Web interface to help users more easily construct the commands necessary to perform benchmark comparisons. ShinyLearner is freely available at <a class=\"link link-uri openInAnotherWindow\" href=\"https://github.com/srp33/ShinyLearner\" target=\"_blank\" rel=\"noopener\" data-google-interstitial=\"false\">https://github.com/srp33/ShinyLearner</a>.</p>\n</div>\n<div class=\" sec\">\n<div class=\"title\"><strong>Conclusions</strong></div>\n<p class=\"chapter-para\">This software is a resource to researchers who wish to benchmark multiple classification or feature-selection algorithms on a given dataset. We hope it will serve as example of combining the benefits of software containerization with a user-friendly approach.</p>\n</div>\n</section>\n<div class=\"article-metadata-panel clearfix at-ArticleMetadata\"> </div>'),
(10, 3, '', 'codeRepository', 'https://github.com/codecheckers/Piccolo-2020'),
(11, 3, '', 'manifestFiles', 'Figures/Datasets_Basic_AUROC.pdf - Figure 2 of manuscript\nFigures/Predictions_Histograms.pdf - Figure 3 of manuscript\nFigures/Algorithms_ParamsImprovement_AUROC.pdf - Figure 4 of manuscript\nFigures/Algorithms_FSImprovement_AUROC.pdf - Figure 5 of manuscript\nFigures/FS_vs_CL.pdf - Figure 6 of manuscript\nFigures/FS_NumFeatures.pdf - Figure 7 of manuscript'),
(12, 3, '', 'dataAvailabilityStatement', 'Code available at https://github.com/codecheckers/Piccolo-2020'),
(13, 4, 'en', 'title', 'Opening practice: supporting reproducibility and critical spatial data science'),
(14, 4, 'en', 'abstract', '<section lang=\"en\" aria-labelledby=\"Abs1\" data-title=\"Abstract\">\n<div id=\"Abs1-section\" class=\"c-article-section\">\n<div id=\"Abs1-content\" class=\"c-article-section__content\">\n<p>This paper reflects on a number of trends towards a more open and reproducible approach to geographic and spatial data science over recent years. In particular, it considers trends towards Big Data, and the impacts this is having on <em>spatial</em> data analysis and modelling. It identifies a turn in academia towards coding as a core analytic tool, and away from proprietary software tools offering ‘black boxes’ where the internal workings of the analysis are not revealed. It is argued that this closed form software is problematic and considers a number of ways in which issues identified in spatial data analysis (such as the MAUP) could be overlooked when working with closed tools, leading to problems of interpretation and possibly inappropriate actions and policies based on these. In addition, this paper considers the role that reproducible and open spatial science may play in such an approach, taking into account the issues raised. It highlights the dangers of failing to account for the geographical properties of data, now that all data are spatial (they are collected some<em>where</em>), the problems of a desire for <span id=\"MathJax-Element-1-Frame\" class=\"MathJax_SVG\" tabindex=\"0\" role=\"presentation\" data-mathml=\"&lt;math xmlns=&quot;http://www.w3.org/1998/Math/MathML&quot;&gt;&lt;mi&gt;n&lt;/mi&gt;&lt;/math&gt;\"></span> <em>n</em> = <em>all</em> observations in data science and it identifies the need for a critical approach. This is one in which openness, transparency, sharing and reproducibility provide a mantra for defensible and robust spatial data science.</p>\n</div>\n</div>\n</section>\n<div data-test=\"cobranding-download\"> </div>'),
(15, 4, '', 'codeRepository', 'https://github.com/lexcomber/OpeningPractice'),
(16, 4, '', 'manifestFiles', 'figure1.png - Figure 1: Housing data and different census areas scales\ntable2.md - Table 2: The model coefficient estimates for the individual input variables\ntable3.md - Table 3: The variable importance (expressed as a percentage)'),
(17, 4, '', 'dataAvailabilityStatement', 'Code available at https://github.com/lexcomber/OpeningPractice'),
(18, 5, 'en', 'title', 'Integrating cellular automata and discrete global grid systems: a case study into wildfire modelling'),
(19, 5, 'en', 'abstract', '<div class=\"card mb-3 flex-grow-1\">\n<div class=\"card-body d-flex flex-column\">\n<div id=\"abstract-section\" class=\"text-container\">\n<div id=\"abstract-content\" class=\"text-box\">\n<p>With new forms of digital spatial data driving new applications for monitoring and understanding environmental change, there are growing demands on traditional GIS tools for spatial data storage, management and processing. Discrete Global Grid System (DGGS) are methods to tessellate globe into multiresolution grids, which represent a global spatial fabric capable of storing heterogeneous spatial data, and improved performance in data access, retrieval, and analysis. While DGGS-based GIS may hold potential for next-generation big data GIS platforms, few of studies have tried to implement them as a framework for operational spatial analysis. Cellular Automata (CA) is a classic dynamic modeling framework which has been used with traditional raster data model for various environmental modeling such as wildfire modeling, urban expansion modeling and so on. The main objectives of this paper are to (i) investigate the possibility of using DGGS for running dynamic spatial analysis, (ii) evaluate CA as a generic data model for dynamic phenomena modeling within a DGGS data model and (iii) evaluate an in-database approach for CA modelling. To do so, a case study into wildfire spread modelling is developed. Results demonstrate that using a DGGS data model not only provides the ability to integrate different data sources, but also provides a framework to do spatial analysis without using geometry-based analysis. This results in a simplified architecture and common spatial fabric to support development of a wide array of spatial algorithms. While considerable work remains to be done, CA modelling within a DGGS-based GIS is a robust and flexible modelling framework for big-data GIS analysis in an environmental monitoring context.</p>\n</div>\n</div>\n</div>\n</div>'),
(20, 5, '', 'codeRepository', 'https://github.com/reproducible-agile/AGILECA'),
(21, 5, '', 'manifestFiles', 'figures/figure1.png - manuscript Figure 1'),
(22, 5, '', 'dataAvailabilityStatement', 'Code available at https://github.com/reproducible-agile/AGILECA'),
(25, 7, 'en', 'title', '\"Landmark Route\": A Comparison to the Shortest Route'),
(26, 7, 'en', 'abstract', '<div class=\"card mb-3 flex-grow-1\">\n<div class=\"card-body d-flex flex-column\">\n<div id=\"abstract-section\" class=\"text-container\">\n<div id=\"abstract-content\" class=\"text-box\">\n<p>Most navigation systems for pedestrians output the shortest route. However, there are findings that travellers do not use the shortest route when free to choose. One alternative to minimising spatial distance is the incorporation of landmark information in a shortest route algorithm. Yet, we do not know whether pedestrians prefer such a landmark route over the shortest route. Therefore, we perform a survey and show participants videos of a shortest and a landmark route. We let participants answer questions concerning navigation satisfaction, route communication, and route comparison. Our findings show that the landmark route is more favourable.</p>\n</div>\n</div>\n</div>\n</div>'),
(27, 7, '', 'codeRepository', 'https://doi.org/10.6084/m9.figshare.19794289.v1'),
(28, 7, '', 'manifestFiles', 'NA'),
(29, 7, '', 'dataAvailabilityStatement', '-'),
(30, 8, 'en', 'title', 'svaRetro and svaNUMT: Modular packages for annotation of retrotransposed transcripts and nuclear integration of mitochondrial DNA in genome sequencing data'),
(31, 8, 'en', 'abstract', '<p>Nuclear integration of mitochondrial genomes and retrocopied transcript insertion are biologically important but often-overlooked aspects of structural variant (SV) annotation. While tools for their detection exist, these typically rely on reanalysis of primary data using specialised detectors rather than leveraging calls from general purpose structural variant callers. Such reanalysis potentially leads to additional computational expense and does not take advantage of advances in general purpose structural variant calling. Here, we present svaRetro and svaNUMT; R packages that provide functions for annotating novel genomic events, such as nonreference retrocopied transcripts and nuclear integration of mitochondrial DNA. The packages were developed to work within the Bioconductor framework. We evaluate the performance of these packages to detect events using simulations and public benchmarking datasets, and annotate processed transcripts in a public structural variant database. svaRetro and svaNUMT provide modular, SV-caller agnostic tools for downstream annotation of structural variant calls.</p>'),
(32, 8, '', 'codeRepository', 'https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT.git'),
(33, 8, '', 'manifestFiles', 'figure-2b.pdf - Figure 2(b) of the article\nfigure-3b.pdf - Figure 3(b) of the article\nfigure-4.pdf - Figure 4 of the article\nfigure-5.pdf - Figure 5 of the article\nfigure-6.pdf - Figure 6 of the article'),
(34, 8, '', 'dataAvailabilityStatement', 'Code available at https://gitlab.com/cdchck/community-codechecks/2022-svaRetro-svaNUMT.git'),
(35, 9, 'en', 'title', 'Geoparsing: Solved or Biased? An Evaluation of Geographic Biases in Geoparsing'),
(36, 9, 'en', 'abstract', '<div class=\"card mb-3 flex-grow-1\">\n<div class=\"card-body d-flex flex-column\">\n<div id=\"abstract-section\" class=\"text-container\">\n<div id=\"abstract-content\" class=\"text-box\">\n<p>Geoparsing, the task of extracting toponyms from texts and associating them with geographic locations, has witnessed remarkable progress over the past years. However, despite its intrinsically geospatial nature, existing evaluations tend to focus on overall performance while paying little attention to its variation across geographic space. In this work, we attempt to answer the question whether geoparsing is solved or biased by conducting a spatially-explicit evaluation, namely an evaluation of the regional variability in geoparsing performance. Particularly, we will analyze the spatial autocorrelation underlying this regional variability. By performing hot and cold spot detection over results of several open-source geoparsers, we observe that none of them performs equally well across geographic space, and some are geographically biased towards some regions but against others. We also carry out a comparative experiment showing that stateof- the-art geoparsers developed with neural networks do not necessarily outperform the off-the-shelf tools across geographic space. To understand the implications behind this observed regional variability, we evaluate geographic biases involved in geoparsing research centered around data contribution and usage, algorithm design, and performance evaluations. Particularly, our spatially-explicit performance evaluation serves as an approach to evaluation bias mitigation in geoparsing.We conclude that previous performance evaluations published in the literature are overly optimistic, thus hiding the fact that geoparsing is far from solved, and geoparsers require debiasing in addition to further considerations when being applied to (geospatial) downstream tasks.</p>\n</div>\n</div>\n</div>\n</div>'),
(37, 9, '', 'codeRepository', 'https://github.com/zilongliu-geo/Geoparsing-Solved-Or-Biased'),
(38, 9, '', 'manifestFiles', 'NA'),
(39, 9, '', 'dataAvailabilityStatement', 'Code available at https://github.com/zilongliu-geo/Geoparsing-Solved-Or-Biased'),
(40, 10, 'en', 'title', 'Urban Sound Mapping for Wayfinding - A theoretical Approach and an empirical Study'),
(41, 10, 'en', 'abstract', '<p>Conventional navigation systems use visually perceptible landmarks to navigate their users from a starting point to a destination. However, sometimes visual information is not enough for route guidance. Visually-impaired or elderly people may not be able to navigate using the visual sense. Furthermore, there may exist no outstanding (i.e., salient) visual landmarks that could be used to navigate. In such a case auditory information may be a helpful guide. We performed two online studies and a focus-group interview to identify possible sound classes in an urban environment. Based on our results, we gathered sounds in Augsburg and classified them according to their source. The findings support our notion that auditory information can be useful for spatial orientation and guidance in addition to or even replacing visual information.</p>'),
(42, 10, '', 'codeRepository', 'https://doi.org/10.6084/m9.figshare.22109987'),
(43, 10, '', 'manifestFiles', 'Na'),
(44, 10, '', 'dataAvailabilityStatement', '-'),
(45, 4, 'en', 'copyrightHolder', 'CODECHECK Demo Journal'),
(46, 4, '', 'copyrightYear', '2026'),
(47, 3, 'en', 'copyrightHolder', 'CODECHECK Demo Journal'),
(48, 3, '', 'copyrightYear', '2026'),
(49, 2, 'en', 'copyrightHolder', 'CODECHECK Demo Journal'),
(50, 2, '', 'copyrightYear', '2026'),
(51, 7, 'en', 'copyrightHolder', 'CODECHECK Demo Journal'),
(52, 7, '', 'copyrightYear', '2026'),
(53, 5, 'en', 'copyrightHolder', 'CODECHECK Demo Journal'),
(54, 5, '', 'copyrightYear', '2026');

-- --------------------------------------------------------

--
-- Table structure for table `queries`
--

CREATE TABLE `queries` (
  `query_id` bigint NOT NULL,
  `assoc_type` bigint NOT NULL,
  `assoc_id` bigint NOT NULL,
  `stage_id` smallint NOT NULL,
  `seq` double NOT NULL DEFAULT '0',
  `date_posted` datetime DEFAULT NULL,
  `date_modified` datetime DEFAULT NULL,
  `closed` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Discussions, usually related to a submission, created by editors, authors and other editorial staff.';

-- --------------------------------------------------------

--
-- Table structure for table `query_participants`
--

CREATE TABLE `query_participants` (
  `query_participant_id` bigint UNSIGNED NOT NULL,
  `query_id` bigint NOT NULL,
  `user_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='The users assigned to a discussion.';

-- --------------------------------------------------------

--
-- Table structure for table `queued_payments`
--

CREATE TABLE `queued_payments` (
  `queued_payment_id` bigint NOT NULL,
  `date_created` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `payment_data` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Unfulfilled (queued) payments, i.e. payments that have not yet been completed via an online payment system.';

-- --------------------------------------------------------

--
-- Table structure for table `reviewer_suggestions`
--

CREATE TABLE `reviewer_suggestions` (
  `reviewer_suggestion_id` bigint NOT NULL,
  `suggesting_user_id` bigint DEFAULT NULL COMMENT 'The user/author who has made the suggestion',
  `submission_id` bigint NOT NULL COMMENT 'Submission at which the suggestion was made',
  `email` varchar(255) NOT NULL COMMENT 'Suggested reviewer email address',
  `orcid_id` varchar(255) DEFAULT NULL COMMENT 'Suggested reviewer optional Orcid Id',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'If and when the suggestion approved to add/invite suggested_reviewer',
  `approver_id` bigint DEFAULT NULL COMMENT 'The user who has approved the suggestion',
  `reviewer_id` bigint DEFAULT NULL COMMENT 'The reviewer who has been added/invited through this suggestion',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Author suggested reviewers at the submission time';

-- --------------------------------------------------------

--
-- Table structure for table `reviewer_suggestion_settings`
--

CREATE TABLE `reviewer_suggestion_settings` (
  `reviewer_suggestion_id` bigint NOT NULL COMMENT 'The foreign key mapping of this setting to reviewer_suggestions table',
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Reviewer suggestion settings table to contain multilingual or extra information';

-- --------------------------------------------------------

--
-- Table structure for table `review_assignments`
--

CREATE TABLE `review_assignments` (
  `review_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `reviewer_id` bigint NOT NULL,
  `competing_interests` text,
  `recommendation` smallint DEFAULT NULL,
  `date_assigned` datetime DEFAULT NULL,
  `date_notified` datetime DEFAULT NULL,
  `date_confirmed` datetime DEFAULT NULL,
  `date_completed` datetime DEFAULT NULL,
  `date_considered` datetime DEFAULT NULL,
  `date_acknowledged` datetime DEFAULT NULL,
  `date_due` datetime DEFAULT NULL,
  `date_response_due` datetime DEFAULT NULL,
  `last_modified` datetime DEFAULT NULL,
  `reminder_was_automatic` smallint NOT NULL DEFAULT '0',
  `declined` smallint NOT NULL DEFAULT '0',
  `cancelled` smallint NOT NULL DEFAULT '0',
  `date_cancelled` datetime DEFAULT NULL,
  `date_rated` datetime DEFAULT NULL,
  `date_reminded` datetime DEFAULT NULL,
  `quality` smallint DEFAULT NULL,
  `review_round_id` bigint NOT NULL,
  `stage_id` smallint NOT NULL,
  `review_method` smallint NOT NULL DEFAULT '1',
  `round` smallint NOT NULL DEFAULT '1',
  `step` smallint NOT NULL DEFAULT '1',
  `review_form_id` bigint DEFAULT NULL,
  `considered` smallint DEFAULT NULL,
  `request_resent` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Data about peer review assignments for all submissions.';

-- --------------------------------------------------------

--
-- Table structure for table `review_assignment_settings`
--

CREATE TABLE `review_assignment_settings` (
  `review_assignment_settings_id` bigint UNSIGNED NOT NULL COMMENT 'Primary key.',
  `review_id` bigint NOT NULL COMMENT 'Foreign key referencing record in review_assignments table',
  `locale` varchar(28) DEFAULT NULL COMMENT 'Locale key.',
  `setting_name` varchar(255) NOT NULL COMMENT 'Name of settings record.',
  `setting_value` mediumtext COMMENT 'Settings value.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `review_files`
--

CREATE TABLE `review_files` (
  `review_file_id` bigint UNSIGNED NOT NULL,
  `review_id` bigint NOT NULL,
  `submission_file_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A list of the submission files made available to each assigned reviewer.';

-- --------------------------------------------------------

--
-- Table structure for table `review_forms`
--

CREATE TABLE `review_forms` (
  `review_form_id` bigint NOT NULL,
  `assoc_type` bigint NOT NULL,
  `assoc_id` bigint NOT NULL,
  `seq` double DEFAULT NULL,
  `is_active` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Review forms provide custom templates for peer reviews with several types of questions.';

-- --------------------------------------------------------

--
-- Table structure for table `review_form_elements`
--

CREATE TABLE `review_form_elements` (
  `review_form_element_id` bigint NOT NULL,
  `review_form_id` bigint NOT NULL,
  `seq` double DEFAULT NULL,
  `element_type` bigint DEFAULT NULL,
  `required` smallint DEFAULT NULL,
  `included` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Each review form element represents a single question on a review form.';

-- --------------------------------------------------------

--
-- Table structure for table `review_form_element_settings`
--

CREATE TABLE `review_form_element_settings` (
  `review_form_element_setting_id` bigint UNSIGNED NOT NULL,
  `review_form_element_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about review form elements, including localized content such as question text.';

-- --------------------------------------------------------

--
-- Table structure for table `review_form_responses`
--

CREATE TABLE `review_form_responses` (
  `review_form_response_id` bigint UNSIGNED NOT NULL,
  `review_form_element_id` bigint NOT NULL,
  `review_id` bigint NOT NULL,
  `response_type` varchar(6) DEFAULT NULL,
  `response_value` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Each review form response records a reviewer''s answer to a review form element associated with a peer review.';

-- --------------------------------------------------------

--
-- Table structure for table `review_form_settings`
--

CREATE TABLE `review_form_settings` (
  `review_form_setting_id` bigint UNSIGNED NOT NULL,
  `review_form_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about review forms, including localized content such as names.';

-- --------------------------------------------------------

--
-- Table structure for table `review_rounds`
--

CREATE TABLE `review_rounds` (
  `review_round_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `stage_id` bigint DEFAULT NULL,
  `round` smallint NOT NULL,
  `review_revision` bigint DEFAULT NULL,
  `status` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Peer review assignments are organized into multiple rounds on a submission.';

--
-- Dumping data for table `review_rounds`
--

INSERT INTO `review_rounds` (`review_round_id`, `submission_id`, `stage_id`, `round`, `review_revision`, `status`) VALUES
(1, 8, 3, 1, NULL, 6),
(2, 9, 3, 1, NULL, 6);

-- --------------------------------------------------------

--
-- Table structure for table `review_round_files`
--

CREATE TABLE `review_round_files` (
  `review_round_file_id` bigint UNSIGNED NOT NULL,
  `submission_id` bigint NOT NULL,
  `review_round_id` bigint NOT NULL,
  `stage_id` smallint NOT NULL,
  `submission_file_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Records the files made available to reviewers for a round of reviews. These can be further customized on a per review basis with review_files.';

--
-- Dumping data for table `review_round_files`
--

INSERT INTO `review_round_files` (`review_round_file_id`, `submission_id`, `review_round_id`, `stage_id`, `submission_file_id`) VALUES
(1, 8, 1, 3, 15),
(2, 9, 2, 3, 16);

-- --------------------------------------------------------

--
-- Table structure for table `rors`
--

CREATE TABLE `rors` (
  `ror_id` bigint NOT NULL,
  `ror` varchar(255) NOT NULL,
  `display_locale` varchar(28) NOT NULL,
  `is_active` smallint NOT NULL DEFAULT '1',
  `search_phrase` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Ror registry dataset cache';

-- --------------------------------------------------------

--
-- Table structure for table `ror_settings`
--

CREATE TABLE `ror_settings` (
  `ror_setting_id` bigint UNSIGNED NOT NULL,
  `ror_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about Ror registry dataset cache';

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` bigint NOT NULL,
  `journal_id` bigint NOT NULL,
  `review_form_id` bigint DEFAULT NULL,
  `seq` double NOT NULL DEFAULT '0',
  `editor_restricted` smallint NOT NULL DEFAULT '0',
  `meta_indexed` smallint NOT NULL DEFAULT '0',
  `meta_reviewed` smallint NOT NULL DEFAULT '1',
  `abstracts_not_required` smallint NOT NULL DEFAULT '0',
  `hide_title` smallint NOT NULL DEFAULT '0',
  `hide_author` smallint NOT NULL DEFAULT '0',
  `is_inactive` smallint NOT NULL DEFAULT '0',
  `abstract_word_count` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A list of all sections into which submissions can be organized, forming the table of contents.';

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`section_id`, `journal_id`, `review_form_id`, `seq`, `editor_restricted`, `meta_indexed`, `meta_reviewed`, `abstracts_not_required`, `hide_title`, `hide_author`, `is_inactive`, `abstract_word_count`) VALUES
(1, 1, NULL, 0, 0, 1, 1, 0, 0, 0, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `section_settings`
--

CREATE TABLE `section_settings` (
  `section_setting_id` bigint UNSIGNED NOT NULL,
  `section_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about sections, including localized properties like section titles.';

--
-- Dumping data for table `section_settings`
--

INSERT INTO `section_settings` (`section_setting_id`, `section_id`, `locale`, `setting_name`, `setting_value`) VALUES
(1, 1, 'en', 'title', 'Articles'),
(2, 1, 'en', 'abbrev', 'ART'),
(3, 1, 'en', 'policy', 'Section default policy');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `last_activity` int NOT NULL,
  `payload` longtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Session data for logged-in users.';

-- --------------------------------------------------------

--
-- Table structure for table `site`
--

CREATE TABLE `site` (
  `site_id` bigint UNSIGNED NOT NULL,
  `redirect_context_id` bigint DEFAULT NULL COMMENT 'If not null, redirect to the specified journal/conference/... site.',
  `primary_locale` varchar(28) NOT NULL COMMENT 'Primary locale for the site.',
  `min_password_length` smallint NOT NULL DEFAULT '6',
  `installed_locales` varchar(1024) NOT NULL DEFAULT 'en' COMMENT 'Locales for which support has been installed.',
  `supported_locales` varchar(1024) DEFAULT NULL COMMENT 'Locales supported by the site (for hosted journals/conferences/...).',
  `original_style_file_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A singleton table describing basic information about the site.';

--
-- Dumping data for table `site`
--

INSERT INTO `site` (`site_id`, `redirect_context_id`, `primary_locale`, `min_password_length`, `installed_locales`, `supported_locales`, `original_style_file_name`) VALUES
(1, NULL, 'en', 6, '[\"de\",\"en\"]', '[\"de\",\"en\"]', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `site_setting_id` bigint UNSIGNED NOT NULL,
  `setting_name` varchar(255) NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about the site, including localized properties such as its name.';

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`site_setting_id`, `setting_name`, `locale`, `setting_value`) VALUES
(1, 'contactEmail', 'en', 'admin@example.com'),
(2, 'contactName', 'de', 'Open Journal Systems'),
(3, 'contactName', 'en', 'Open Journal Systems'),
(4, 'compressStatsLogs', '', '0'),
(5, 'enableGeoUsageStats', '', 'disabled'),
(6, 'enableInstitutionUsageStats', '', '0'),
(7, 'keepDailyUsageStats', '', '0'),
(8, 'isSiteSushiPlatform', '', '0'),
(9, 'isSushiApiPublic', '', '1'),
(10, 'disableSharedReviewerStatistics', '', '0'),
(11, 'orcidClientId', '', ''),
(12, 'orcidClientSecret', '', ''),
(13, 'orcidEnabled', '', '0'),
(14, 'themePluginPath', '', 'default'),
(15, 'uniqueSiteId', '', 'BA8C3270-D0BB-43C5-B350-8AAA7767061C');

-- --------------------------------------------------------

--
-- Table structure for table `stage_assignments`
--

CREATE TABLE `stage_assignments` (
  `stage_assignment_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `user_group_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `date_assigned` datetime NOT NULL,
  `recommend_only` smallint NOT NULL DEFAULT '0',
  `can_change_metadata` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Who can access a submission while it is in the editorial workflow. Includes all editorial and author assignments. For reviewers, see review_assignments.';

--
-- Dumping data for table `stage_assignments`
--

INSERT INTO `stage_assignments` (`stage_assignment_id`, `submission_id`, `user_group_id`, `user_id`, `date_assigned`, `recommend_only`, `can_change_metadata`) VALUES
(2, 2, 14, 3, '2026-02-26 00:15:15', 0, 0),
(3, 3, 14, 3, '2026-02-26 00:57:30', 0, 0),
(4, 4, 14, 2, '2026-02-26 01:11:52', 0, 0),
(5, 5, 14, 2, '2026-02-26 01:18:39', 0, 0),
(7, 7, 14, 4, '2026-02-26 01:43:31', 0, 0),
(8, 8, 14, 3, '2026-02-26 01:53:06', 0, 0),
(9, 9, 14, 2, '2026-02-26 02:04:52', 0, 0),
(10, 10, 14, 4, '2026-02-26 02:12:34', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `static_pages`
--

CREATE TABLE `static_pages` (
  `static_page_id` bigint NOT NULL,
  `path` varchar(255) NOT NULL,
  `context_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `static_page_settings`
--

CREATE TABLE `static_page_settings` (
  `static_page_setting_id` bigint UNSIGNED NOT NULL,
  `static_page_id` bigint NOT NULL,
  `locale` varchar(14) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` longtext,
  `setting_type` varchar(6) NOT NULL COMMENT '(bool|int|float|string|object)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Table structure for table `subeditor_submission_group`
--

CREATE TABLE `subeditor_submission_group` (
  `subeditor_submission_group_id` bigint UNSIGNED NOT NULL,
  `context_id` bigint NOT NULL,
  `assoc_id` bigint NOT NULL,
  `assoc_type` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `user_group_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Subeditor assignments to e.g. sections and categories';

-- --------------------------------------------------------

--
-- Table structure for table `submissions`
--

CREATE TABLE `submissions` (
  `submission_id` bigint NOT NULL,
  `context_id` bigint NOT NULL,
  `current_publication_id` bigint DEFAULT NULL,
  `date_last_activity` datetime DEFAULT NULL,
  `date_submitted` datetime DEFAULT NULL,
  `last_modified` datetime DEFAULT NULL,
  `stage_id` bigint NOT NULL DEFAULT '1',
  `locale` varchar(28) DEFAULT NULL,
  `status` smallint NOT NULL DEFAULT '1',
  `submission_progress` varchar(50) NOT NULL DEFAULT 'start',
  `work_type` smallint DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='All submissions submitted to the context, including incomplete, declined and unpublished submissions.';

--
-- Dumping data for table `submissions`
--

INSERT INTO `submissions` (`submission_id`, `context_id`, `current_publication_id`, `date_last_activity`, `date_submitted`, `last_modified`, `stage_id`, `locale`, `status`, `submission_progress`, `work_type`) VALUES
(2, 1, 2, '2026-02-26 02:34:25', '2026-02-26 00:55:09', '2026-02-26 00:55:09', 4, 'en', 3, '', 0),
(3, 1, 3, '2026-02-26 02:34:25', '2026-02-26 01:08:10', '2026-02-26 01:08:10', 4, 'en', 3, '', 0),
(4, 1, 4, '2026-02-26 02:34:25', '2026-02-26 01:15:55', '2026-02-26 01:15:55', 4, 'en', 3, '', 0),
(5, 1, 5, '2026-02-26 02:34:32', '2026-02-26 01:24:39', '2026-02-26 01:24:39', 4, 'en', 3, '', 0),
(7, 1, 7, '2026-02-26 02:34:32', '2026-02-26 01:48:06', '2026-02-26 01:48:06', 4, 'en', 3, '', 0),
(8, 1, 8, '2026-02-26 02:31:38', '2026-02-26 02:00:07', '2026-02-26 02:00:07', 3, 'en', 1, '', 0),
(9, 1, 9, '2026-02-26 02:32:11', '2026-02-26 02:11:20', '2026-02-26 02:11:20', 3, 'en', 1, '', 0),
(10, 1, 10, '2026-02-26 02:18:24', '2026-02-26 02:18:23', '2026-02-26 02:18:23', 1, 'en', 1, '', 0);

-- --------------------------------------------------------

--
-- Table structure for table `submission_comments`
--

CREATE TABLE `submission_comments` (
  `comment_id` bigint NOT NULL,
  `comment_type` bigint DEFAULT NULL,
  `role_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `assoc_id` bigint NOT NULL,
  `author_id` bigint NOT NULL,
  `comment_title` text NOT NULL,
  `comments` text,
  `date_posted` datetime DEFAULT NULL,
  `date_modified` datetime DEFAULT NULL,
  `viewable` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Comments on a submission, e.g. peer review comments';

-- --------------------------------------------------------

--
-- Table structure for table `submission_files`
--

CREATE TABLE `submission_files` (
  `submission_file_id` bigint UNSIGNED NOT NULL,
  `submission_id` bigint NOT NULL,
  `file_id` bigint UNSIGNED NOT NULL,
  `source_submission_file_id` bigint UNSIGNED DEFAULT NULL,
  `genre_id` bigint DEFAULT NULL,
  `file_stage` bigint NOT NULL,
  `direct_sales_price` varchar(255) DEFAULT NULL,
  `sales_type` varchar(255) DEFAULT NULL,
  `viewable` smallint DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `uploader_user_id` bigint DEFAULT NULL,
  `assoc_type` bigint DEFAULT NULL,
  `assoc_id` bigint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='All files associated with a submission, such as those uploaded during submission, as revisions, or by copyeditors or layout editors for production.';

--
-- Dumping data for table `submission_files`
--

INSERT INTO `submission_files` (`submission_file_id`, `submission_id`, `file_id`, `source_submission_file_id`, `genre_id`, `file_stage`, `direct_sales_price`, `sales_type`, `viewable`, `created_at`, `updated_at`, `uploader_user_id`, `assoc_type`, `assoc_id`) VALUES
(2, 2, 2, NULL, 1, 2, NULL, NULL, NULL, '2026-02-26 00:21:41', '2026-02-26 00:21:42', 3, NULL, NULL),
(3, 3, 3, NULL, 1, 2, NULL, NULL, NULL, '2026-02-26 01:03:47', '2026-02-26 01:03:54', 3, NULL, NULL),
(4, 4, 4, NULL, 1, 2, NULL, NULL, NULL, '2026-02-26 01:14:25', '2026-02-26 01:14:26', 2, NULL, NULL),
(5, 5, 5, NULL, 1, 2, NULL, NULL, NULL, '2026-02-26 01:23:49', '2026-02-26 01:23:51', 2, NULL, NULL),
(6, 7, 6, NULL, 1, 2, NULL, NULL, NULL, '2026-02-26 01:46:21', '2026-02-26 01:46:25', 4, NULL, NULL),
(7, 8, 7, NULL, 1, 2, NULL, NULL, NULL, '2026-02-26 01:57:55', '2026-02-26 01:57:57', 3, NULL, NULL),
(8, 9, 8, NULL, 1, 2, NULL, NULL, NULL, '2026-02-26 02:06:43', '2026-02-26 02:06:44', 2, NULL, NULL),
(9, 10, 9, NULL, 1, 2, NULL, NULL, NULL, '2026-02-26 02:15:17', '2026-02-26 02:15:19', 4, NULL, NULL),
(10, 2, 2, 2, 1, 6, NULL, NULL, NULL, '2026-02-26 02:24:17', '2026-02-26 02:24:17', 3, NULL, NULL),
(11, 3, 3, 3, 1, 6, NULL, NULL, NULL, '2026-02-26 02:26:42', '2026-02-26 02:26:42', 3, NULL, NULL),
(12, 4, 4, 4, 1, 6, NULL, NULL, NULL, '2026-02-26 02:27:23', '2026-02-26 02:27:23', 2, NULL, NULL),
(13, 5, 5, 5, 1, 6, NULL, NULL, NULL, '2026-02-26 02:29:14', '2026-02-26 02:29:14', 2, NULL, NULL),
(14, 7, 6, 6, 1, 6, NULL, NULL, NULL, '2026-02-26 02:29:42', '2026-02-26 02:29:42', 4, NULL, NULL),
(15, 8, 7, 7, 1, 4, NULL, NULL, NULL, '2026-02-26 02:31:38', '2026-02-26 02:31:38', 3, 523, 1),
(16, 9, 8, 8, 1, 4, NULL, NULL, NULL, '2026-02-26 02:32:11', '2026-02-26 02:32:11', 2, 523, 2);

-- --------------------------------------------------------

--
-- Table structure for table `submission_file_revisions`
--

CREATE TABLE `submission_file_revisions` (
  `revision_id` bigint UNSIGNED NOT NULL,
  `submission_file_id` bigint UNSIGNED NOT NULL,
  `file_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Revisions map submission_file entries to files on the data store.';

--
-- Dumping data for table `submission_file_revisions`
--

INSERT INTO `submission_file_revisions` (`revision_id`, `submission_file_id`, `file_id`) VALUES
(2, 2, 2),
(3, 3, 3),
(4, 4, 4),
(5, 5, 5),
(6, 6, 6),
(7, 7, 7),
(8, 8, 8),
(9, 9, 9),
(10, 10, 2),
(11, 11, 3),
(12, 12, 4),
(13, 13, 5),
(14, 14, 6),
(15, 15, 7),
(16, 16, 8);

-- --------------------------------------------------------

--
-- Table structure for table `submission_file_settings`
--

CREATE TABLE `submission_file_settings` (
  `submission_file_setting_id` bigint UNSIGNED NOT NULL,
  `submission_file_id` bigint UNSIGNED NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Localized data about submission files like published metadata.';

--
-- Dumping data for table `submission_file_settings`
--

INSERT INTO `submission_file_settings` (`submission_file_setting_id`, `submission_file_id`, `locale`, `setting_name`, `setting_value`) VALUES
(2, 2, 'en', 'name', 'sample.pdf'),
(3, 3, 'en', 'name', 'sample.pdf'),
(4, 4, 'en', 'name', 'sample.pdf'),
(5, 5, 'en', 'name', 'sample.pdf'),
(6, 6, 'en', 'name', 'sample.pdf'),
(7, 7, 'en', 'name', 'sample.pdf'),
(8, 8, 'en', 'name', 'sample.pdf'),
(9, 9, 'en', 'name', 'sample.pdf'),
(10, 10, 'en', 'name', 'sample.pdf'),
(11, 11, 'en', 'name', 'sample.pdf'),
(12, 12, 'en', 'name', 'sample.pdf'),
(13, 13, 'en', 'name', 'sample.pdf'),
(14, 14, 'en', 'name', 'sample.pdf'),
(15, 15, 'en', 'name', 'sample.pdf'),
(16, 16, 'en', 'name', 'sample.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `submission_search_keyword_list`
--

CREATE TABLE `submission_search_keyword_list` (
  `keyword_id` bigint NOT NULL,
  `keyword_text` varchar(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A list of all keywords used in the search index';

--
-- Dumping data for table `submission_search_keyword_list`
--

INSERT INTO `submission_search_keyword_list` (`keyword_id`, `keyword_text`) VALUES
(365, 'ability'),
(211, 'abs1'),
(52, 'abstract'),
(227, 'academia'),
(339, 'access'),
(259, 'account'),
(80, 'accuracy'),
(461, 'achieve'),
(407, 'acquire'),
(464, 'acquired'),
(254, 'actions'),
(98, 'addition'),
(527, 'additional'),
(127, 'address'),
(439, 'advancements'),
(531, 'advances'),
(530, 'advantage'),
(412, 'aerial'),
(79, 'affect'),
(405, 'agencies'),
(545, 'agnostic'),
(429, 'aim'),
(386, 'airborne'),
(292, 'alexis'),
(77, 'algorithm'),
(63, 'algorithms'),
(410, 'als'),
(474, 'alternative'),
(444, 'altogether'),
(9, 'analyse'),
(223, 'analysis'),
(230, 'analytic'),
(585, 'analyze'),
(542, 'annotate'),
(535, 'annotating'),
(496, 'annotation'),
(482, 'answer'),
(552, 'anthony'),
(313, 'applications'),
(93, 'applied'),
(85, 'apply'),
(188, 'approach'),
(370, 'architecture'),
(239, 'argued'),
(53, 'aria'),
(124, 'arise'),
(375, 'array'),
(602, 'art'),
(189, 'article'),
(193, 'articlemetadata'),
(510, 'aspects'),
(64, 'assign'),
(561, 'associating'),
(578, 'attempt'),
(575, 'attention'),
(659, 'auditory'),
(671, 'augsburg'),
(586, 'autocorrelation'),
(295, 'automata'),
(60, 'background'),
(38, 'baddeley'),
(67, 'based'),
(87, 'basis'),
(551, 'bedo'),
(90, 'benchmark'),
(43, 'benchmarking'),
(184, 'benefits'),
(616, 'bias'),
(556, 'biased'),
(557, 'biases'),
(221, 'big'),
(539, 'bioconductor'),
(509, 'biologically'),
(234, 'black'),
(172, 'blank'),
(306, 'body'),
(309, 'box'),
(235, 'boxes'),
(291, 'brunsdon'),
(384, 'building'),
(424, 'buildings'),
(426, 'cadastre'),
(635, 'cai'),
(544, 'caller'),
(524, 'callers'),
(532, 'calling'),
(521, 'calls'),
(549, 'cameron'),
(334, 'capable'),
(303, 'card'),
(597, 'carry'),
(300, 'case'),
(445, 'cell'),
(294, 'cellular'),
(611, 'centered'),
(128, 'challenges'),
(419, 'chance'),
(317, 'change'),
(61, 'chapter'),
(78, 'choice'),
(473, 'choose'),
(290, 'chris'),
(51, 'class'),
(667, 'classes'),
(348, 'classic'),
(47, 'classification'),
(672, 'classified'),
(192, 'clearfix'),
(240, 'closed'),
(409, 'cloud'),
(392, 'clouds'),
(288, 'cobranding'),
(228, 'coding'),
(589, 'cold'),
(266, 'collected'),
(307, 'column'),
(293, 'comber'),
(460, 'combination'),
(101, 'combinations'),
(183, 'combining'),
(162, 'commands'),
(371, 'common'),
(485, 'communication'),
(69, 'community'),
(598, 'comparative'),
(113, 'compare'),
(467, 'comparison'),
(110, 'comparisons'),
(4, 'components'),
(528, 'computational'),
(618, 'conclude'),
(178, 'conclusions'),
(580, 'conducting'),
(123, 'conflicts'),
(376, 'considerable'),
(628, 'considerations'),
(220, 'considers'),
(161, 'construct'),
(428, 'consuming'),
(308, 'container'),
(185, 'containerization'),
(42, 'containerized'),
(134, 'containers'),
(212, 'content'),
(380, 'context'),
(612, 'contribution'),
(645, 'conventional'),
(27, 'convolution'),
(229, 'core'),
(19, 'cortex'),
(402, 'couple'),
(129, 'created'),
(207, 'critical'),
(147, 'cross'),
(82, 'crucial'),
(262, 'dangers'),
(201, 'daniel'),
(49, 'data'),
(362, 'database'),
(399, 'databases'),
(180, 'dataset'),
(94, 'datasets'),
(627, 'debiasing'),
(286, 'defensible'),
(319, 'demands'),
(364, 'demonstrate'),
(417, 'dems'),
(389, 'dense'),
(436, 'densities'),
(443, 'density'),
(23, 'depend'),
(122, 'dependency'),
(26, 'dependent'),
(13, 'derivatives'),
(413, 'deriving'),
(614, 'design'),
(267, 'desire'),
(651, 'destination'),
(420, 'detect'),
(395, 'detecting'),
(385, 'detection'),
(431, 'detections'),
(519, 'detectors'),
(70, 'developed'),
(373, 'development'),
(326, 'dggs'),
(121, 'differ'),
(111, 'difficult'),
(311, 'digital'),
(411, 'dim'),
(105, 'dimensionality'),
(296, 'discrete'),
(450, 'distance'),
(446, 'distribution'),
(56, 'div'),
(72, 'diverse'),
(502, 'dna'),
(86, 'domain'),
(76, 'domains'),
(548, 'dong'),
(289, 'download'),
(546, 'downstream'),
(81, 'dramatically'),
(312, 'driving'),
(437, 'due'),
(349, 'dynamic'),
(160, 'easily'),
(2, 'eglen'),
(654, 'elderly'),
(269, 'element'),
(415, 'elevation'),
(88, 'empirical'),
(142, 'enables'),
(668, 'environment'),
(316, 'environmental'),
(594, 'equally'),
(196, 'erica'),
(487, 'eva'),
(99, 'evaluate'),
(119, 'evaluation'),
(571, 'evaluations'),
(537, 'events'),
(89, 'evidence'),
(96, 'examines'),
(513, 'exist'),
(570, 'existing'),
(354, 'expansion'),
(529, 'expense'),
(599, 'experiment'),
(582, 'explicit'),
(447, 'exploiting'),
(558, 'extracting'),
(333, 'fabric'),
(141, 'facilitating'),
(625, 'fact'),
(263, 'failing'),
(177, 'false'),
(486, 'favourable'),
(102, 'feature'),
(145, 'features'),
(153, 'files'),
(400, 'find'),
(126, 'findings'),
(304, 'flex'),
(379, 'flexible'),
(573, 'focus'),
(241, 'form'),
(118, 'formats'),
(310, 'forms'),
(17, 'found'),
(270, 'frame'),
(346, 'framework'),
(382, 'frank'),
(489, 'franziska'),
(472, 'free'),
(163, 'freely'),
(187, 'friendly'),
(534, 'functions'),
(34, 'gaps'),
(669, 'gathered'),
(14, 'gaussian'),
(522, 'general'),
(151, 'generates'),
(343, 'generation'),
(359, 'generic'),
(638, 'gengchen'),
(503, 'genome'),
(505, 'genomes'),
(536, 'genomic'),
(217, 'geographic'),
(264, 'geographical'),
(595, 'geographically'),
(368, 'geometry'),
(591, 'geoparsers'),
(554, 'geoparsing'),
(568, 'geospatial'),
(321, 'gis'),
(169, 'github'),
(297, 'global'),
(329, 'globe'),
(175, 'google'),
(298, 'grid'),
(331, 'grids'),
(664, 'group'),
(66, 'groups'),
(305, 'grow'),
(318, 'growing'),
(652, 'guidance'),
(661, 'guide'),
(677, 'hamburger'),
(36, 'hancock'),
(381, 'header'),
(393, 'height'),
(660, 'helpful'),
(336, 'heterogeneous'),
(624, 'hiding'),
(261, 'highlights'),
(25, 'highly'),
(199, 'hill'),
(341, 'hold'),
(181, 'hope'),
(588, 'hot'),
(167, 'href'),
(278, 'http'),
(168, 'https'),
(100, 'hyperparameter'),
(143, 'hyperparameters'),
(245, 'identified'),
(225, 'identifies'),
(666, 'identify'),
(361, 'iii'),
(29, 'image'),
(6, 'images'),
(222, 'impacts'),
(653, 'impaired'),
(345, 'implement'),
(107, 'implementations'),
(140, 'implements'),
(608, 'implications'),
(396, 'important'),
(337, 'improved'),
(253, 'inappropriate'),
(157, 'includes'),
(476, 'incorporation'),
(442, 'independent'),
(20, 'inferred'),
(394, 'information'),
(508, 'insertion'),
(125, 'installation'),
(366, 'integrate'),
(133, 'integrating'),
(500, 'integration'),
(32, 'inter'),
(136, 'interface'),
(117, 'interfaces'),
(236, 'internal'),
(251, 'interpretation'),
(176, 'interstitial'),
(665, 'interview'),
(567, 'intrinsically'),
(356, 'investigate'),
(610, 'involved'),
(401, 'irregularities'),
(138, 'irrespective'),
(244, 'issues'),
(633, 'janowicz'),
(448, 'jensen'),
(550, 'justin'),
(676, 'kai'),
(198, 'kimball'),
(490, 'könig'),
(632, 'krzysztof'),
(54, 'label'),
(210, 'labelledby'),
(465, 'landmark'),
(648, 'landmarks'),
(209, 'lang'),
(423, 'large'),
(387, 'laser'),
(249, 'leading'),
(526, 'leads'),
(46, 'learning'),
(195, 'lee'),
(39, 'leslie'),
(520, 'leveraging'),
(139, 'library'),
(73, 'life'),
(634, 'ling'),
(164, 'link'),
(621, 'literature'),
(631, 'liu'),
(562, 'locations'),
(45, 'machine'),
(639, 'mai'),
(55, 'main'),
(154, 'make'),
(323, 'management'),
(285, 'mantra'),
(427, 'manually'),
(404, 'mapping'),
(390, 'matching'),
(275, 'math'),
(268, 'mathjax'),
(274, 'mathml'),
(246, 'maup'),
(451, 'measure'),
(462, 'measured'),
(640, 'meilin'),
(190, 'metadata'),
(454, 'method'),
(327, 'methods'),
(475, 'minimising'),
(617, 'mitigation'),
(501, 'mitochondrial'),
(352, 'model'),
(350, 'modeling'),
(224, 'modelling'),
(416, 'models'),
(495, 'modular'),
(314, 'monitoring'),
(92, 'multiple'),
(330, 'multiresolution'),
(71, 'myriad'),
(408, 'nation'),
(403, 'national'),
(5, 'natural'),
(569, 'nature'),
(649, 'navigate'),
(469, 'navigation'),
(604, 'necessarily'),
(146, 'nested'),
(8, 'net'),
(603, 'networks'),
(7, 'neural'),
(406, 'nmas'),
(538, 'nonreference'),
(174, 'noopener'),
(673, 'notion'),
(499, 'nuclear'),
(488, 'nuhn'),
(215, 'number'),
(202, 'nüst'),
(425, 'object'),
(355, 'objectives'),
(65, 'observations'),
(592, 'observe'),
(609, 'observed'),
(418, 'offer'),
(233, 'offering'),
(663, 'online'),
(130, 'open'),
(166, 'openinanotherwindow'),
(203, 'opening'),
(281, 'openness'),
(347, 'operational'),
(150, 'operations'),
(15, 'operators'),
(623, 'optimistic'),
(84, 'optimize'),
(433, 'order'),
(280, 'org'),
(674, 'orientation'),
(28, 'original'),
(383, 'ostermann'),
(605, 'outperform'),
(455, 'outperforms'),
(152, 'output'),
(657, 'outstanding'),
(247, 'overlooked'),
(622, 'overly'),
(115, 'packages'),
(191, 'panel'),
(553, 'papenfuss'),
(213, 'paper'),
(62, 'para'),
(480, 'participants'),
(566, 'past'),
(68, 'patterns'),
(574, 'paying'),
(470, 'pedestrians'),
(655, 'people'),
(647, 'perceptible'),
(112, 'perform'),
(338, 'performance'),
(662, 'performed'),
(137, 'performing'),
(593, 'performs'),
(35, 'peter'),
(360, 'phenomena'),
(200, 'piccolo'),
(344, 'platforms'),
(257, 'play'),
(391, 'point'),
(421, 'points'),
(255, 'policies'),
(357, 'possibility'),
(252, 'possibly'),
(342, 'potential'),
(525, 'potentially'),
(204, 'practice'),
(477, 'prefer'),
(533, 'present'),
(273, 'presentation'),
(619, 'previous'),
(517, 'primary'),
(3, 'principal'),
(242, 'problematic'),
(250, 'problems'),
(120, 'procedures'),
(543, 'processed'),
(324, 'processing'),
(414, 'products'),
(116, 'programming'),
(565, 'progress'),
(132, 'project'),
(265, 'properties'),
(441, 'propose'),
(453, 'proposed'),
(231, 'proprietary'),
(284, 'provide'),
(21, 'psychophysics'),
(541, 'public'),
(620, 'published'),
(523, 'purpose'),
(579, 'question'),
(483, 'questions'),
(277, 'quot'),
(260, 'raised'),
(351, 'raster'),
(516, 'reanalysis'),
(218, 'recent'),
(104, 'reduce'),
(214, 'reflects'),
(583, 'regional'),
(596, 'regions'),
(173, 'rel'),
(430, 'reliable'),
(515, 'rely'),
(378, 'remains'),
(564, 'remarkable'),
(675, 'replacing'),
(332, 'represent'),
(206, 'reproducibility'),
(216, 'reproducible'),
(626, 'require'),
(75, 'research'),
(95, 'researcher'),
(83, 'researchers'),
(12, 'resemble'),
(179, 'resource'),
(458, 'respect'),
(22, 'results'),
(340, 'retrieval'),
(506, 'retrocopied'),
(497, 'retrotransposed'),
(238, 'revealed'),
(109, 'robust'),
(37, 'roland'),
(256, 'role'),
(466, 'route'),
(636, 'rui'),
(547, 'ruining'),
(358, 'running'),
(491, 'sabine'),
(658, 'salient'),
(10, 'samples'),
(484, 'satisfaction'),
(24, 'scale'),
(388, 'scanning'),
(74, 'science'),
(463, 'score'),
(57, 'sec'),
(50, 'section'),
(144, 'select'),
(103, 'selection'),
(656, 'sense'),
(31, 'sensitive'),
(440, 'sensors'),
(504, 'sequencing'),
(182, 'serve'),
(615, 'serves'),
(449, 'shannon'),
(283, 'sharing'),
(606, 'shelf'),
(641, 'shi'),
(41, 'shinylearner'),
(468, 'shortest'),
(479, 'show'),
(600, 'showing'),
(30, 'shows'),
(16, 'similar'),
(452, 'similarity'),
(456, 'simple'),
(369, 'simplified'),
(540, 'simulations'),
(432, 'sizes'),
(40, 'smith'),
(106, 'software'),
(555, 'solved'),
(642, 'sound'),
(670, 'sounds'),
(131, 'source'),
(367, 'sources'),
(577, 'space'),
(114, 'span'),
(208, 'spatial'),
(581, 'spatially'),
(518, 'specialised'),
(590, 'spot'),
(363, 'spread'),
(170, 'srp33'),
(650, 'starting'),
(601, 'stateof'),
(1, 'stephen'),
(155, 'steps'),
(322, 'storage'),
(335, 'storing'),
(59, 'strong'),
(511, 'structural'),
(91, 'studies'),
(301, 'study'),
(197, 'suh'),
(372, 'support'),
(205, 'supporting'),
(478, 'survey'),
(494, 'svanumt'),
(493, 'svaretro'),
(271, 'svg'),
(325, 'system'),
(299, 'systems'),
(272, 'tabindex'),
(48, 'tabular'),
(258, 'taking'),
(171, 'target'),
(397, 'task'),
(629, 'tasks'),
(438, 'technological'),
(572, 'tend'),
(194, 'terry'),
(328, 'tessellate'),
(287, 'test'),
(11, 'text'),
(560, 'texts'),
(644, 'theoretical'),
(457, 'threshold'),
(422, 'time'),
(434, 'times'),
(492, 'timpf'),
(58, 'title'),
(44, 'tool'),
(232, 'tools'),
(559, 'toponyms'),
(149, 'tracks'),
(320, 'traditional'),
(507, 'transcript'),
(498, 'transcripts'),
(282, 'transparency'),
(156, 'transparent'),
(471, 'travellers'),
(97, 'trends'),
(226, 'turn'),
(459, 'types'),
(514, 'typically'),
(587, 'underlying'),
(607, 'understand'),
(315, 'understanding'),
(135, 'uniform'),
(398, 'update'),
(353, 'urban'),
(165, 'uri'),
(613, 'usage'),
(186, 'user'),
(159, 'users'),
(148, 'validation'),
(584, 'variability'),
(512, 'variant'),
(576, 'variation'),
(435, 'varying'),
(481, 'videos'),
(18, 'visual'),
(646, 'visually'),
(643, 'wayfinding'),
(243, 'ways'),
(158, 'web'),
(374, 'wide'),
(108, 'widely'),
(302, 'wildfire'),
(563, 'witnessed'),
(33, 'word'),
(377, 'work'),
(248, 'working'),
(237, 'workings'),
(279, 'www'),
(276, 'xmlns'),
(219, 'years'),
(637, 'zhu'),
(630, 'zilong');

-- --------------------------------------------------------

--
-- Table structure for table `submission_search_objects`
--

CREATE TABLE `submission_search_objects` (
  `object_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `type` int NOT NULL COMMENT 'Type of item. E.g., abstract, fulltext, etc.',
  `assoc_id` bigint DEFAULT NULL COMMENT 'Optional ID of an associated record (e.g., a file_id)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A list of all search objects indexed in the search index';

--
-- Dumping data for table `submission_search_objects`
--

INSERT INTO `submission_search_objects` (`object_id`, `submission_id`, `type`, `assoc_id`) VALUES
(57, 8, 1, NULL),
(58, 8, 2, NULL),
(59, 8, 4, NULL),
(60, 8, 16, NULL),
(61, 8, 17, NULL),
(62, 8, 8, NULL),
(63, 8, 32, NULL),
(64, 8, 64, NULL),
(65, 9, 1, NULL),
(66, 9, 2, NULL),
(67, 9, 4, NULL),
(68, 9, 16, NULL),
(69, 9, 17, NULL),
(70, 9, 8, NULL),
(71, 9, 32, NULL),
(72, 9, 64, NULL),
(73, 10, 1, NULL),
(74, 10, 2, NULL),
(75, 10, 4, NULL),
(76, 10, 16, NULL),
(77, 10, 17, NULL),
(78, 10, 8, NULL),
(79, 10, 32, NULL),
(80, 10, 64, NULL),
(81, 4, 1, NULL),
(82, 4, 2, NULL),
(83, 4, 4, NULL),
(84, 4, 16, NULL),
(85, 4, 17, NULL),
(86, 4, 8, NULL),
(87, 4, 32, NULL),
(88, 4, 64, NULL),
(89, 3, 1, NULL),
(90, 3, 2, NULL),
(91, 3, 4, NULL),
(92, 3, 16, NULL),
(93, 3, 17, NULL),
(94, 3, 8, NULL),
(95, 3, 32, NULL),
(96, 3, 64, NULL),
(97, 2, 1, NULL),
(98, 2, 2, NULL),
(99, 2, 4, NULL),
(100, 2, 16, NULL),
(101, 2, 17, NULL),
(102, 2, 8, NULL),
(103, 2, 32, NULL),
(104, 2, 64, NULL),
(105, 7, 1, NULL),
(106, 7, 2, NULL),
(107, 7, 4, NULL),
(108, 7, 16, NULL),
(109, 7, 17, NULL),
(110, 7, 8, NULL),
(111, 7, 32, NULL),
(112, 7, 64, NULL),
(113, 5, 1, NULL),
(114, 5, 2, NULL),
(115, 5, 4, NULL),
(116, 5, 16, NULL),
(117, 5, 17, NULL),
(118, 5, 8, NULL),
(119, 5, 32, NULL),
(120, 5, 64, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `submission_search_object_keywords`
--

CREATE TABLE `submission_search_object_keywords` (
  `submission_search_object_keyword_id` bigint UNSIGNED NOT NULL,
  `object_id` bigint NOT NULL,
  `keyword_id` bigint NOT NULL,
  `pos` int NOT NULL COMMENT 'Word position of the keyword in the object.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Relationships between search objects and keywords in the search index';

--
-- Dumping data for table `submission_search_object_keywords`
--

INSERT INTO `submission_search_object_keywords` (`submission_search_object_keyword_id`, `object_id`, `keyword_id`, `pos`) VALUES
(5779, 57, 547, 0),
(5780, 57, 548, 1),
(5781, 57, 201, 2),
(5782, 57, 549, 3),
(5783, 57, 550, 4),
(5784, 57, 551, 5),
(5785, 57, 552, 6),
(5786, 57, 553, 7),
(5787, 58, 493, 0),
(5788, 58, 494, 1),
(5789, 58, 495, 2),
(5790, 58, 115, 3),
(5791, 58, 496, 4),
(5792, 58, 497, 5),
(5793, 58, 498, 6),
(5794, 58, 499, 7),
(5795, 58, 500, 8),
(5796, 58, 501, 9),
(5797, 58, 502, 10),
(5798, 58, 503, 11),
(5799, 58, 504, 12),
(5800, 58, 49, 13),
(5801, 59, 499, 0),
(5802, 59, 500, 1),
(5803, 59, 501, 2),
(5804, 59, 505, 3),
(5805, 59, 506, 4),
(5806, 59, 507, 5),
(5807, 59, 508, 6),
(5808, 59, 509, 7),
(5809, 59, 396, 8),
(5810, 59, 247, 9),
(5811, 59, 510, 10),
(5812, 59, 511, 11),
(5813, 59, 512, 12),
(5814, 59, 496, 13),
(5815, 59, 232, 14),
(5816, 59, 385, 15),
(5817, 59, 513, 16),
(5818, 59, 514, 17),
(5819, 59, 515, 18),
(5820, 59, 516, 19),
(5821, 59, 517, 20),
(5822, 59, 49, 21),
(5823, 59, 518, 22),
(5824, 59, 519, 23),
(5825, 59, 520, 24),
(5826, 59, 521, 25),
(5827, 59, 522, 26),
(5828, 59, 523, 27),
(5829, 59, 511, 28),
(5830, 59, 512, 29),
(5831, 59, 524, 30),
(5832, 59, 516, 31),
(5833, 59, 525, 32),
(5834, 59, 526, 33),
(5835, 59, 527, 34),
(5836, 59, 528, 35),
(5837, 59, 529, 36),
(5838, 59, 530, 37),
(5839, 59, 531, 38),
(5840, 59, 522, 39),
(5841, 59, 523, 40),
(5842, 59, 511, 41),
(5843, 59, 512, 42),
(5844, 59, 532, 43),
(5845, 59, 533, 44),
(5846, 59, 493, 45),
(5847, 59, 494, 46),
(5848, 59, 115, 47),
(5849, 59, 284, 48),
(5850, 59, 534, 49),
(5851, 59, 535, 50),
(5852, 59, 536, 51),
(5853, 59, 537, 52),
(5854, 59, 538, 53),
(5855, 59, 506, 54),
(5856, 59, 498, 55),
(5857, 59, 499, 56),
(5858, 59, 500, 57),
(5859, 59, 501, 58),
(5860, 59, 502, 59),
(5861, 59, 115, 60),
(5862, 59, 70, 61),
(5863, 59, 377, 62),
(5864, 59, 539, 63),
(5865, 59, 346, 64),
(5866, 59, 99, 65),
(5867, 59, 338, 66),
(5868, 59, 115, 67),
(5869, 59, 420, 68),
(5870, 59, 537, 69),
(5871, 59, 540, 70),
(5872, 59, 541, 71),
(5873, 59, 43, 72),
(5874, 59, 94, 73),
(5875, 59, 542, 74),
(5876, 59, 543, 75),
(5877, 59, 498, 76),
(5878, 59, 541, 77),
(5879, 59, 511, 78),
(5880, 59, 512, 79),
(5881, 59, 362, 80),
(5882, 59, 493, 81),
(5883, 59, 494, 82),
(5884, 59, 284, 83),
(5885, 59, 495, 84),
(5886, 59, 544, 85),
(5887, 59, 545, 86),
(5888, 59, 232, 87),
(5889, 59, 546, 88),
(5890, 59, 496, 89),
(5891, 59, 511, 90),
(5892, 59, 512, 91),
(5893, 59, 521, 92),
(6753, 65, 630, 0),
(6754, 65, 631, 1),
(6755, 65, 632, 2),
(6756, 65, 633, 3),
(6757, 65, 634, 4),
(6758, 65, 635, 5),
(6759, 65, 636, 6),
(6760, 65, 637, 7),
(6761, 65, 638, 8),
(6762, 65, 639, 9),
(6763, 65, 640, 10),
(6764, 65, 641, 11),
(6765, 66, 554, 0),
(6766, 66, 555, 1),
(6767, 66, 556, 2),
(6768, 66, 119, 3),
(6769, 66, 217, 4),
(6770, 66, 557, 5),
(6771, 66, 554, 6),
(6772, 67, 56, 0),
(6773, 67, 51, 1),
(6774, 67, 303, 2),
(6775, 67, 304, 3),
(6776, 67, 305, 4),
(6777, 67, 56, 5),
(6778, 67, 51, 6),
(6779, 67, 303, 7),
(6780, 67, 306, 8),
(6781, 67, 304, 9),
(6782, 67, 304, 10),
(6783, 67, 307, 11),
(6784, 67, 56, 12),
(6785, 67, 52, 13),
(6786, 67, 50, 14),
(6787, 67, 51, 15),
(6788, 67, 11, 16),
(6789, 67, 308, 17),
(6790, 67, 56, 18),
(6791, 67, 52, 19),
(6792, 67, 212, 20),
(6793, 67, 51, 21),
(6794, 67, 11, 22),
(6795, 67, 309, 23),
(6796, 67, 554, 24),
(6797, 67, 397, 25),
(6798, 67, 558, 26),
(6799, 67, 559, 27),
(6800, 67, 560, 28),
(6801, 67, 561, 29),
(6802, 67, 217, 30),
(6803, 67, 562, 31),
(6804, 67, 563, 32),
(6805, 67, 564, 33),
(6806, 67, 565, 34),
(6807, 67, 566, 35),
(6808, 67, 219, 36),
(6809, 67, 567, 37),
(6810, 67, 568, 38),
(6811, 67, 569, 39),
(6812, 67, 570, 40),
(6813, 67, 571, 41),
(6814, 67, 572, 42),
(6815, 67, 573, 43),
(6816, 67, 338, 44),
(6817, 67, 574, 45),
(6818, 67, 575, 46),
(6819, 67, 576, 47),
(6820, 67, 217, 48),
(6821, 67, 577, 49),
(6822, 67, 377, 50),
(6823, 67, 578, 51),
(6824, 67, 482, 52),
(6825, 67, 579, 53),
(6826, 67, 554, 54),
(6827, 67, 555, 55),
(6828, 67, 556, 56),
(6829, 67, 580, 57),
(6830, 67, 581, 58),
(6831, 67, 582, 59),
(6832, 67, 119, 60),
(6833, 67, 119, 61),
(6834, 67, 583, 62),
(6835, 67, 584, 63),
(6836, 67, 554, 64),
(6837, 67, 338, 65),
(6838, 67, 585, 66),
(6839, 67, 208, 67),
(6840, 67, 586, 68),
(6841, 67, 587, 69),
(6842, 67, 583, 70),
(6843, 67, 584, 71),
(6844, 67, 137, 72),
(6845, 67, 588, 73),
(6846, 67, 589, 74),
(6847, 67, 590, 75),
(6848, 67, 385, 76),
(6849, 67, 22, 77),
(6850, 67, 130, 78),
(6851, 67, 131, 79),
(6852, 67, 591, 80),
(6853, 67, 592, 81),
(6854, 67, 593, 82),
(6855, 67, 594, 83),
(6856, 67, 217, 84),
(6857, 67, 577, 85),
(6858, 67, 595, 86),
(6859, 67, 556, 87),
(6860, 67, 596, 88),
(6861, 67, 597, 89),
(6862, 67, 598, 90),
(6863, 67, 599, 91),
(6864, 67, 600, 92),
(6865, 67, 601, 93),
(6866, 67, 602, 94),
(6867, 67, 591, 95),
(6868, 67, 70, 96),
(6869, 67, 7, 97),
(6870, 67, 603, 98),
(6871, 67, 604, 99),
(6872, 67, 605, 100),
(6873, 67, 606, 101),
(6874, 67, 232, 102),
(6875, 67, 217, 103),
(6876, 67, 577, 104),
(6877, 67, 607, 105),
(6878, 67, 608, 106),
(6879, 67, 609, 107),
(6880, 67, 583, 108),
(6881, 67, 584, 109),
(6882, 67, 99, 110),
(6883, 67, 217, 111),
(6884, 67, 557, 112),
(6885, 67, 610, 113),
(6886, 67, 554, 114),
(6887, 67, 75, 115),
(6888, 67, 611, 116),
(6889, 67, 49, 117),
(6890, 67, 612, 118),
(6891, 67, 613, 119),
(6892, 67, 77, 120),
(6893, 67, 614, 121),
(6894, 67, 338, 122),
(6895, 67, 571, 123),
(6896, 67, 581, 124),
(6897, 67, 582, 125),
(6898, 67, 338, 126),
(6899, 67, 119, 127),
(6900, 67, 615, 128),
(6901, 67, 188, 129),
(6902, 67, 119, 130),
(6903, 67, 616, 131),
(6904, 67, 617, 132),
(6905, 67, 554, 133),
(6906, 67, 618, 134),
(6907, 67, 619, 135),
(6908, 67, 338, 136),
(6909, 67, 571, 137),
(6910, 67, 620, 138),
(6911, 67, 621, 139),
(6912, 67, 622, 140),
(6913, 67, 623, 141),
(6914, 67, 624, 142),
(6915, 67, 625, 143),
(6916, 67, 554, 144),
(6917, 67, 555, 145),
(6918, 67, 591, 146),
(6919, 67, 626, 147),
(6920, 67, 627, 148),
(6921, 67, 98, 149),
(6922, 67, 628, 150),
(6923, 67, 93, 151),
(6924, 67, 568, 152),
(6925, 67, 546, 153),
(6926, 67, 629, 154),
(6927, 67, 56, 155),
(6928, 67, 56, 156),
(6929, 67, 56, 157),
(6930, 67, 56, 158),
(7310, 73, 487, 0),
(7311, 73, 488, 1),
(7312, 73, 676, 2),
(7313, 73, 677, 3),
(7314, 73, 491, 4),
(7315, 73, 492, 5),
(7316, 74, 353, 0),
(7317, 74, 642, 1),
(7318, 74, 404, 2),
(7319, 74, 643, 3),
(7320, 74, 644, 4),
(7321, 74, 188, 5),
(7322, 74, 88, 6),
(7323, 74, 301, 7),
(7324, 75, 645, 0),
(7325, 75, 469, 1),
(7326, 75, 299, 2),
(7327, 75, 646, 3),
(7328, 75, 647, 4),
(7329, 75, 648, 5),
(7330, 75, 649, 6),
(7331, 75, 159, 7),
(7332, 75, 650, 8),
(7333, 75, 391, 9),
(7334, 75, 651, 10),
(7335, 75, 18, 11),
(7336, 75, 394, 12),
(7337, 75, 466, 13),
(7338, 75, 652, 14),
(7339, 75, 646, 15),
(7340, 75, 653, 16),
(7341, 75, 654, 17),
(7342, 75, 655, 18),
(7343, 75, 649, 19),
(7344, 75, 18, 20),
(7345, 75, 656, 21),
(7346, 75, 513, 22),
(7347, 75, 657, 23),
(7348, 75, 658, 24),
(7349, 75, 18, 25),
(7350, 75, 648, 26),
(7351, 75, 649, 27),
(7352, 75, 300, 28),
(7353, 75, 659, 29),
(7354, 75, 394, 30),
(7355, 75, 660, 31),
(7356, 75, 661, 32),
(7357, 75, 662, 33),
(7358, 75, 663, 34),
(7359, 75, 91, 35),
(7360, 75, 573, 36),
(7361, 75, 664, 37),
(7362, 75, 665, 38),
(7363, 75, 666, 39),
(7364, 75, 642, 40),
(7365, 75, 667, 41),
(7366, 75, 353, 42),
(7367, 75, 668, 43),
(7368, 75, 67, 44),
(7369, 75, 22, 45),
(7370, 75, 669, 46),
(7371, 75, 670, 47),
(7372, 75, 671, 48),
(7373, 75, 672, 49),
(7374, 75, 131, 50),
(7375, 75, 126, 51),
(7376, 75, 372, 52),
(7377, 75, 673, 53),
(7378, 75, 659, 54),
(7379, 75, 394, 55),
(7380, 75, 208, 56),
(7381, 75, 674, 57),
(7382, 75, 652, 58),
(7383, 75, 98, 59),
(7384, 75, 675, 60),
(7385, 75, 18, 61),
(7386, 75, 394, 62),
(8330, 81, 290, 0),
(8331, 81, 291, 1),
(8332, 81, 292, 2),
(8333, 81, 293, 3),
(8334, 82, 203, 0),
(8335, 82, 204, 1),
(8336, 82, 205, 2),
(8337, 82, 206, 3),
(8338, 82, 207, 4),
(8339, 82, 208, 5),
(8340, 82, 49, 6),
(8341, 82, 74, 7),
(8342, 83, 50, 0),
(8343, 83, 209, 1),
(8344, 83, 53, 2),
(8345, 83, 210, 3),
(8346, 83, 211, 4),
(8347, 83, 49, 5),
(8348, 83, 58, 6),
(8349, 83, 52, 7),
(8350, 83, 56, 8),
(8351, 83, 211, 9),
(8352, 83, 50, 10),
(8353, 83, 51, 11),
(8354, 83, 189, 12),
(8355, 83, 50, 13),
(8356, 83, 56, 14),
(8357, 83, 211, 15),
(8358, 83, 212, 16),
(8359, 83, 51, 17),
(8360, 83, 189, 18),
(8361, 83, 50, 19),
(8362, 83, 212, 20),
(8363, 83, 213, 21),
(8364, 83, 214, 22),
(8365, 83, 215, 23),
(8366, 83, 97, 24),
(8367, 83, 130, 25),
(8368, 83, 216, 26),
(8369, 83, 188, 27),
(8370, 83, 217, 28),
(8371, 83, 208, 29),
(8372, 83, 49, 30),
(8373, 83, 74, 31),
(8374, 83, 218, 32),
(8375, 83, 219, 33),
(8376, 83, 220, 34),
(8377, 83, 97, 35),
(8378, 83, 221, 36),
(8379, 83, 49, 37),
(8380, 83, 222, 38),
(8381, 83, 208, 39),
(8382, 83, 49, 40),
(8383, 83, 223, 41),
(8384, 83, 224, 42),
(8385, 83, 225, 43),
(8386, 83, 226, 44),
(8387, 83, 227, 45),
(8388, 83, 228, 46),
(8389, 83, 229, 47),
(8390, 83, 230, 48),
(8391, 83, 44, 49),
(8392, 83, 231, 50),
(8393, 83, 106, 51),
(8394, 83, 232, 52),
(8395, 83, 233, 53),
(8396, 83, 234, 54),
(8397, 83, 235, 55),
(8398, 83, 236, 56),
(8399, 83, 237, 57),
(8400, 83, 223, 58),
(8401, 83, 238, 59),
(8402, 83, 239, 60),
(8403, 83, 240, 61),
(8404, 83, 241, 62),
(8405, 83, 106, 63),
(8406, 83, 242, 64),
(8407, 83, 220, 65),
(8408, 83, 215, 66),
(8409, 83, 243, 67),
(8410, 83, 244, 68),
(8411, 83, 245, 69),
(8412, 83, 208, 70),
(8413, 83, 49, 71),
(8414, 83, 223, 72),
(8415, 83, 246, 73),
(8416, 83, 247, 74),
(8417, 83, 248, 75),
(8418, 83, 240, 76),
(8419, 83, 232, 77),
(8420, 83, 249, 78),
(8421, 83, 250, 79),
(8422, 83, 251, 80),
(8423, 83, 252, 81),
(8424, 83, 253, 82),
(8425, 83, 254, 83),
(8426, 83, 255, 84),
(8427, 83, 67, 85),
(8428, 83, 98, 86),
(8429, 83, 213, 87),
(8430, 83, 220, 88),
(8431, 83, 256, 89),
(8432, 83, 216, 90),
(8433, 83, 130, 91),
(8434, 83, 208, 92),
(8435, 83, 74, 93),
(8436, 83, 257, 94),
(8437, 83, 188, 95),
(8438, 83, 258, 96),
(8439, 83, 259, 97),
(8440, 83, 244, 98),
(8441, 83, 260, 99),
(8442, 83, 261, 100),
(8443, 83, 262, 101),
(8444, 83, 263, 102),
(8445, 83, 259, 103),
(8446, 83, 264, 104),
(8447, 83, 265, 105),
(8448, 83, 49, 106),
(8449, 83, 49, 107),
(8450, 83, 208, 108),
(8451, 83, 266, 109),
(8452, 83, 250, 110),
(8453, 83, 267, 111),
(8454, 83, 114, 112),
(8455, 83, 268, 113),
(8456, 83, 269, 114),
(8457, 83, 270, 115),
(8458, 83, 51, 116),
(8459, 83, 268, 117),
(8460, 83, 271, 118),
(8461, 83, 272, 119),
(8462, 83, 256, 120),
(8463, 83, 273, 121),
(8464, 83, 49, 122),
(8465, 83, 274, 123),
(8466, 83, 275, 124),
(8467, 83, 276, 125),
(8468, 83, 277, 126),
(8469, 83, 278, 127),
(8470, 83, 279, 128),
(8471, 83, 280, 129),
(8472, 83, 275, 130),
(8473, 83, 274, 131),
(8474, 83, 277, 132),
(8475, 83, 275, 133),
(8476, 83, 114, 134),
(8477, 83, 65, 135),
(8478, 83, 49, 136),
(8479, 83, 74, 137),
(8480, 83, 225, 138),
(8481, 83, 207, 139),
(8482, 83, 188, 140),
(8483, 83, 281, 141),
(8484, 83, 282, 142),
(8485, 83, 283, 143),
(8486, 83, 206, 144),
(8487, 83, 284, 145),
(8488, 83, 285, 146),
(8489, 83, 286, 147),
(8490, 83, 109, 148),
(8491, 83, 208, 149),
(8492, 83, 49, 150),
(8493, 83, 74, 151),
(8494, 83, 56, 152),
(8495, 83, 56, 153),
(8496, 83, 50, 154),
(8497, 83, 56, 155),
(8498, 83, 49, 156),
(8499, 83, 287, 157),
(8500, 83, 288, 158),
(8501, 83, 289, 159),
(8502, 83, 56, 160),
(8766, 89, 194, 0),
(8767, 89, 195, 1),
(8768, 89, 196, 2),
(8769, 89, 197, 3),
(8770, 89, 198, 4),
(8771, 89, 199, 5),
(8772, 89, 1, 6),
(8773, 89, 200, 7),
(8774, 90, 41, 0),
(8775, 90, 42, 1),
(8776, 90, 43, 2),
(8777, 90, 44, 3),
(8778, 90, 45, 4),
(8779, 90, 46, 5),
(8780, 90, 47, 6),
(8781, 90, 48, 7),
(8782, 90, 49, 8),
(8783, 91, 50, 0),
(8784, 91, 51, 1),
(8785, 91, 52, 2),
(8786, 91, 53, 3),
(8787, 91, 54, 4),
(8788, 91, 55, 5),
(8789, 91, 52, 6),
(8790, 91, 56, 7),
(8791, 91, 51, 8),
(8792, 91, 57, 9),
(8793, 91, 56, 10),
(8794, 91, 51, 11),
(8795, 91, 58, 12),
(8796, 91, 59, 13),
(8797, 91, 60, 14),
(8798, 91, 59, 15),
(8799, 91, 56, 16),
(8800, 91, 51, 17),
(8801, 91, 61, 18),
(8802, 91, 62, 19),
(8803, 91, 47, 20),
(8804, 91, 63, 21),
(8805, 91, 64, 22),
(8806, 91, 65, 23),
(8807, 91, 66, 24),
(8808, 91, 67, 25),
(8809, 91, 68, 26),
(8810, 91, 49, 27),
(8811, 91, 45, 28),
(8812, 91, 46, 29),
(8813, 91, 69, 30),
(8814, 91, 70, 31),
(8815, 91, 71, 32),
(8816, 91, 47, 33),
(8817, 91, 63, 34),
(8818, 91, 72, 35),
(8819, 91, 73, 36),
(8820, 91, 74, 37),
(8821, 91, 75, 38),
(8822, 91, 76, 39),
(8823, 91, 77, 40),
(8824, 91, 78, 41),
(8825, 91, 79, 42),
(8826, 91, 47, 43),
(8827, 91, 80, 44),
(8828, 91, 81, 45),
(8829, 91, 82, 46),
(8830, 91, 83, 47),
(8831, 91, 84, 48),
(8832, 91, 78, 49),
(8833, 91, 77, 50),
(8834, 91, 85, 51),
(8835, 91, 75, 52),
(8836, 91, 86, 53),
(8837, 91, 87, 54),
(8838, 91, 88, 55),
(8839, 91, 89, 56),
(8840, 91, 90, 57),
(8841, 91, 91, 58),
(8842, 91, 92, 59),
(8843, 91, 63, 60),
(8844, 91, 93, 61),
(8845, 91, 92, 62),
(8846, 91, 94, 63),
(8847, 91, 95, 64),
(8848, 91, 96, 65),
(8849, 91, 97, 66),
(8850, 91, 98, 67),
(8851, 91, 95, 68),
(8852, 91, 99, 69),
(8853, 91, 92, 70),
(8854, 91, 100, 71),
(8855, 91, 101, 72),
(8856, 91, 77, 73),
(8857, 91, 102, 74),
(8858, 91, 103, 75),
(8859, 91, 104, 76),
(8860, 91, 49, 77),
(8861, 91, 105, 78),
(8862, 91, 106, 79),
(8863, 91, 107, 80),
(8864, 91, 47, 81),
(8865, 91, 63, 82),
(8866, 91, 108, 83),
(8867, 91, 109, 84),
(8868, 91, 90, 85),
(8869, 91, 110, 86),
(8870, 91, 111, 87),
(8871, 91, 112, 88),
(8872, 91, 83, 89),
(8873, 91, 113, 90),
(8874, 91, 63, 91),
(8875, 91, 114, 92),
(8876, 91, 92, 93),
(8877, 91, 106, 94),
(8878, 91, 115, 95),
(8879, 91, 116, 96),
(8880, 91, 117, 97),
(8881, 91, 49, 98),
(8882, 91, 118, 99),
(8883, 91, 119, 100),
(8884, 91, 120, 101),
(8885, 91, 121, 102),
(8886, 91, 106, 103),
(8887, 91, 115, 104),
(8888, 91, 122, 105),
(8889, 91, 123, 106),
(8890, 91, 124, 107),
(8891, 91, 125, 108),
(8892, 91, 56, 109),
(8893, 91, 56, 110),
(8894, 91, 51, 111),
(8895, 91, 57, 112),
(8896, 91, 56, 113),
(8897, 91, 51, 114),
(8898, 91, 58, 115),
(8899, 91, 59, 116),
(8900, 91, 126, 117),
(8901, 91, 59, 118),
(8902, 91, 56, 119),
(8903, 91, 51, 120),
(8904, 91, 61, 121),
(8905, 91, 62, 122),
(8906, 91, 127, 123),
(8907, 91, 128, 124),
(8908, 91, 129, 125),
(8909, 91, 41, 126),
(8910, 91, 130, 127),
(8911, 91, 131, 128),
(8912, 91, 132, 129),
(8913, 91, 133, 130),
(8914, 91, 45, 131),
(8915, 91, 46, 132),
(8916, 91, 115, 133),
(8917, 91, 106, 134),
(8918, 91, 134, 135),
(8919, 91, 41, 136),
(8920, 91, 135, 137),
(8921, 91, 136, 138),
(8922, 91, 137, 139),
(8923, 91, 47, 140),
(8924, 91, 138, 141),
(8925, 91, 139, 142),
(8926, 91, 140, 143),
(8927, 91, 77, 144),
(8928, 91, 141, 145),
(8929, 91, 90, 146),
(8930, 91, 110, 147),
(8931, 91, 98, 148),
(8932, 91, 41, 149),
(8933, 91, 142, 150),
(8934, 91, 83, 151),
(8935, 91, 84, 152),
(8936, 91, 143, 153),
(8937, 91, 144, 154),
(8938, 91, 145, 155),
(8939, 91, 146, 156),
(8940, 91, 147, 157),
(8941, 91, 148, 158),
(8942, 91, 149, 159),
(8943, 91, 146, 160),
(8944, 91, 150, 161),
(8945, 91, 151, 162),
(8946, 91, 152, 163),
(8947, 91, 153, 164),
(8948, 91, 154, 165),
(8949, 91, 155, 166),
(8950, 91, 156, 167),
(8951, 91, 41, 168),
(8952, 91, 157, 169),
(8953, 91, 158, 170),
(8954, 91, 136, 171),
(8955, 91, 159, 172),
(8956, 91, 160, 173),
(8957, 91, 161, 174),
(8958, 91, 162, 175),
(8959, 91, 112, 176),
(8960, 91, 90, 177),
(8961, 91, 110, 178),
(8962, 91, 41, 179),
(8963, 91, 163, 180),
(8964, 91, 51, 181),
(8965, 91, 164, 182),
(8966, 91, 164, 183),
(8967, 91, 165, 184),
(8968, 91, 166, 185),
(8969, 91, 167, 186),
(8970, 91, 168, 187),
(8971, 91, 169, 188),
(8972, 91, 170, 189),
(8973, 91, 41, 190),
(8974, 91, 171, 191),
(8975, 91, 172, 192),
(8976, 91, 173, 193),
(8977, 91, 174, 194),
(8978, 91, 49, 195),
(8979, 91, 175, 196),
(8980, 91, 176, 197),
(8981, 91, 177, 198),
(8982, 91, 168, 199),
(8983, 91, 169, 200),
(8984, 91, 170, 201),
(8985, 91, 41, 202),
(8986, 91, 56, 203),
(8987, 91, 56, 204),
(8988, 91, 51, 205),
(8989, 91, 57, 206),
(8990, 91, 56, 207),
(8991, 91, 51, 208),
(8992, 91, 58, 209),
(8993, 91, 59, 210),
(8994, 91, 178, 211),
(8995, 91, 59, 212),
(8996, 91, 56, 213),
(8997, 91, 51, 214),
(8998, 91, 61, 215),
(8999, 91, 62, 216),
(9000, 91, 106, 217),
(9001, 91, 179, 218),
(9002, 91, 83, 219),
(9003, 91, 90, 220),
(9004, 91, 92, 221),
(9005, 91, 47, 222),
(9006, 91, 102, 223),
(9007, 91, 103, 224),
(9008, 91, 63, 225),
(9009, 91, 180, 226),
(9010, 91, 181, 227),
(9011, 91, 182, 228),
(9012, 91, 183, 229),
(9013, 91, 184, 230),
(9014, 91, 106, 231),
(9015, 91, 185, 232),
(9016, 91, 186, 233),
(9017, 91, 187, 234),
(9018, 91, 188, 235),
(9019, 91, 56, 236),
(9020, 91, 50, 237),
(9021, 91, 56, 238),
(9022, 91, 51, 239),
(9023, 91, 189, 240),
(9024, 91, 190, 241),
(9025, 91, 191, 242),
(9026, 91, 192, 243),
(9027, 91, 193, 244),
(9028, 91, 56, 245),
(9079, 97, 35, 0),
(9080, 97, 36, 1),
(9081, 97, 37, 2),
(9082, 97, 38, 3),
(9083, 97, 39, 4),
(9084, 97, 40, 5),
(9085, 98, 3, 0),
(9086, 98, 4, 1),
(9087, 98, 5, 2),
(9088, 98, 6, 3),
(9089, 99, 7, 0),
(9090, 99, 8, 1),
(9091, 99, 9, 2),
(9092, 99, 10, 3),
(9093, 99, 5, 4),
(9094, 99, 6, 5),
(9095, 99, 11, 6),
(9096, 99, 5, 7),
(9097, 99, 6, 8),
(9098, 99, 4, 9),
(9099, 99, 12, 10),
(9100, 99, 13, 11),
(9101, 99, 14, 12),
(9102, 99, 15, 13),
(9103, 99, 16, 14),
(9104, 99, 17, 15),
(9105, 99, 18, 16),
(9106, 99, 19, 17),
(9107, 99, 20, 18),
(9108, 99, 21, 19),
(9109, 99, 22, 20),
(9110, 99, 5, 21),
(9111, 99, 6, 22),
(9112, 99, 23, 23),
(9113, 99, 24, 24),
(9114, 99, 11, 25),
(9115, 99, 6, 26),
(9116, 99, 25, 27),
(9117, 99, 24, 28),
(9118, 99, 26, 29),
(9119, 99, 27, 30),
(9120, 99, 11, 31),
(9121, 99, 4, 32),
(9122, 99, 28, 33),
(9123, 99, 29, 34),
(9124, 99, 30, 35),
(9125, 99, 31, 36),
(9126, 99, 32, 37),
(9127, 99, 33, 38),
(9128, 99, 34, 39),
(9218, 105, 487, 0),
(9219, 105, 488, 1),
(9220, 105, 489, 2),
(9221, 105, 490, 3),
(9222, 105, 491, 4),
(9223, 105, 492, 5),
(9224, 106, 465, 0),
(9225, 106, 466, 1),
(9226, 106, 467, 2),
(9227, 106, 468, 3),
(9228, 106, 466, 4),
(9229, 107, 56, 0),
(9230, 107, 51, 1),
(9231, 107, 303, 2),
(9232, 107, 304, 3),
(9233, 107, 305, 4),
(9234, 107, 56, 5),
(9235, 107, 51, 6),
(9236, 107, 303, 7),
(9237, 107, 306, 8),
(9238, 107, 304, 9),
(9239, 107, 304, 10),
(9240, 107, 307, 11),
(9241, 107, 56, 12),
(9242, 107, 52, 13),
(9243, 107, 50, 14),
(9244, 107, 51, 15),
(9245, 107, 11, 16),
(9246, 107, 308, 17),
(9247, 107, 56, 18),
(9248, 107, 52, 19),
(9249, 107, 212, 20),
(9250, 107, 51, 21),
(9251, 107, 11, 22),
(9252, 107, 309, 23),
(9253, 107, 469, 24),
(9254, 107, 299, 25),
(9255, 107, 470, 26),
(9256, 107, 152, 27),
(9257, 107, 468, 28),
(9258, 107, 466, 29),
(9259, 107, 126, 30),
(9260, 107, 471, 31),
(9261, 107, 468, 32),
(9262, 107, 466, 33),
(9263, 107, 472, 34),
(9264, 107, 473, 35),
(9265, 107, 474, 36),
(9266, 107, 475, 37),
(9267, 107, 208, 38),
(9268, 107, 450, 39),
(9269, 107, 476, 40),
(9270, 107, 465, 41),
(9271, 107, 394, 42),
(9272, 107, 468, 43),
(9273, 107, 466, 44),
(9274, 107, 77, 45),
(9275, 107, 470, 46),
(9276, 107, 477, 47),
(9277, 107, 465, 48),
(9278, 107, 466, 49),
(9279, 107, 468, 50),
(9280, 107, 466, 51),
(9281, 107, 112, 52),
(9282, 107, 478, 53),
(9283, 107, 479, 54),
(9284, 107, 480, 55),
(9285, 107, 481, 56),
(9286, 107, 468, 57),
(9287, 107, 465, 58),
(9288, 107, 466, 59),
(9289, 107, 480, 60),
(9290, 107, 482, 61),
(9291, 107, 483, 62),
(9292, 107, 469, 63),
(9293, 107, 484, 64),
(9294, 107, 466, 65),
(9295, 107, 485, 66),
(9296, 107, 466, 67),
(9297, 107, 467, 68),
(9298, 107, 126, 69),
(9299, 107, 479, 70),
(9300, 107, 465, 71),
(9301, 107, 466, 72),
(9302, 107, 486, 73),
(9303, 107, 56, 74),
(9304, 107, 56, 75),
(9305, 107, 56, 76),
(9306, 107, 56, 77),
(9502, 113, 201, 0),
(9503, 113, 202, 1),
(9504, 114, 133, 0),
(9505, 114, 294, 1),
(9506, 114, 295, 2),
(9507, 114, 296, 3),
(9508, 114, 297, 4),
(9509, 114, 298, 5),
(9510, 114, 299, 6),
(9511, 114, 300, 7),
(9512, 114, 301, 8),
(9513, 114, 302, 9),
(9514, 114, 224, 10),
(9515, 115, 56, 0),
(9516, 115, 51, 1),
(9517, 115, 303, 2),
(9518, 115, 304, 3),
(9519, 115, 305, 4),
(9520, 115, 56, 5),
(9521, 115, 51, 6),
(9522, 115, 303, 7),
(9523, 115, 306, 8),
(9524, 115, 304, 9),
(9525, 115, 304, 10),
(9526, 115, 307, 11),
(9527, 115, 56, 12),
(9528, 115, 52, 13),
(9529, 115, 50, 14),
(9530, 115, 51, 15),
(9531, 115, 11, 16),
(9532, 115, 308, 17),
(9533, 115, 56, 18),
(9534, 115, 52, 19),
(9535, 115, 212, 20),
(9536, 115, 51, 21),
(9537, 115, 11, 22),
(9538, 115, 309, 23),
(9539, 115, 310, 24),
(9540, 115, 311, 25),
(9541, 115, 208, 26),
(9542, 115, 49, 27),
(9543, 115, 312, 28),
(9544, 115, 313, 29),
(9545, 115, 314, 30),
(9546, 115, 315, 31),
(9547, 115, 316, 32),
(9548, 115, 317, 33),
(9549, 115, 318, 34),
(9550, 115, 319, 35),
(9551, 115, 320, 36),
(9552, 115, 321, 37),
(9553, 115, 232, 38),
(9554, 115, 208, 39),
(9555, 115, 49, 40),
(9556, 115, 322, 41),
(9557, 115, 323, 42),
(9558, 115, 324, 43),
(9559, 115, 296, 44),
(9560, 115, 297, 45),
(9561, 115, 298, 46),
(9562, 115, 325, 47),
(9563, 115, 326, 48),
(9564, 115, 327, 49),
(9565, 115, 328, 50),
(9566, 115, 329, 51),
(9567, 115, 330, 52),
(9568, 115, 331, 53),
(9569, 115, 332, 54),
(9570, 115, 297, 55),
(9571, 115, 208, 56),
(9572, 115, 333, 57),
(9573, 115, 334, 58),
(9574, 115, 335, 59),
(9575, 115, 336, 60),
(9576, 115, 208, 61),
(9577, 115, 49, 62),
(9578, 115, 337, 63),
(9579, 115, 338, 64),
(9580, 115, 49, 65),
(9581, 115, 339, 66),
(9582, 115, 340, 67),
(9583, 115, 223, 68),
(9584, 115, 326, 69),
(9585, 115, 67, 70),
(9586, 115, 321, 71),
(9587, 115, 341, 72),
(9588, 115, 342, 73),
(9589, 115, 343, 74),
(9590, 115, 221, 75),
(9591, 115, 49, 76),
(9592, 115, 321, 77),
(9593, 115, 344, 78),
(9594, 115, 91, 79),
(9595, 115, 345, 80),
(9596, 115, 346, 81),
(9597, 115, 347, 82),
(9598, 115, 208, 83),
(9599, 115, 223, 84),
(9600, 115, 294, 85),
(9601, 115, 295, 86),
(9602, 115, 348, 87),
(9603, 115, 349, 88),
(9604, 115, 350, 89),
(9605, 115, 346, 90),
(9606, 115, 320, 91),
(9607, 115, 351, 92),
(9608, 115, 49, 93),
(9609, 115, 352, 94),
(9610, 115, 316, 95),
(9611, 115, 350, 96),
(9612, 115, 302, 97),
(9613, 115, 350, 98),
(9614, 115, 353, 99),
(9615, 115, 354, 100),
(9616, 115, 350, 101),
(9617, 115, 55, 102),
(9618, 115, 355, 103),
(9619, 115, 213, 104),
(9620, 115, 356, 105),
(9621, 115, 357, 106),
(9622, 115, 326, 107),
(9623, 115, 358, 108),
(9624, 115, 349, 109),
(9625, 115, 208, 110),
(9626, 115, 223, 111),
(9627, 115, 99, 112),
(9628, 115, 359, 113),
(9629, 115, 49, 114),
(9630, 115, 352, 115),
(9631, 115, 349, 116),
(9632, 115, 360, 117),
(9633, 115, 350, 118),
(9634, 115, 326, 119),
(9635, 115, 49, 120),
(9636, 115, 352, 121),
(9637, 115, 361, 122),
(9638, 115, 99, 123),
(9639, 115, 362, 124),
(9640, 115, 188, 125),
(9641, 115, 224, 126),
(9642, 115, 300, 127),
(9643, 115, 301, 128),
(9644, 115, 302, 129),
(9645, 115, 363, 130),
(9646, 115, 224, 131),
(9647, 115, 70, 132),
(9648, 115, 22, 133),
(9649, 115, 364, 134),
(9650, 115, 326, 135),
(9651, 115, 49, 136),
(9652, 115, 352, 137),
(9653, 115, 365, 138),
(9654, 115, 366, 139),
(9655, 115, 49, 140),
(9656, 115, 367, 141),
(9657, 115, 346, 142),
(9658, 115, 208, 143),
(9659, 115, 223, 144),
(9660, 115, 368, 145),
(9661, 115, 67, 146),
(9662, 115, 223, 147),
(9663, 115, 22, 148),
(9664, 115, 369, 149),
(9665, 115, 370, 150),
(9666, 115, 371, 151),
(9667, 115, 208, 152),
(9668, 115, 333, 153),
(9669, 115, 372, 154),
(9670, 115, 373, 155),
(9671, 115, 374, 156),
(9672, 115, 375, 157),
(9673, 115, 208, 158),
(9674, 115, 63, 159),
(9675, 115, 376, 160),
(9676, 115, 377, 161),
(9677, 115, 378, 162),
(9678, 115, 224, 163),
(9679, 115, 326, 164),
(9680, 115, 67, 165),
(9681, 115, 321, 166),
(9682, 115, 109, 167),
(9683, 115, 379, 168),
(9684, 115, 224, 169),
(9685, 115, 346, 170),
(9686, 115, 221, 171),
(9687, 115, 49, 172),
(9688, 115, 321, 173),
(9689, 115, 223, 174),
(9690, 115, 316, 175),
(9691, 115, 314, 176),
(9692, 115, 380, 177),
(9693, 115, 56, 178),
(9694, 115, 56, 179),
(9695, 115, 56, 180),
(9696, 115, 56, 181);

-- --------------------------------------------------------

--
-- Table structure for table `submission_settings`
--

CREATE TABLE `submission_settings` (
  `submission_setting_id` bigint UNSIGNED NOT NULL,
  `submission_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Localized data about submissions';

--
-- Dumping data for table `submission_settings`
--

INSERT INTO `submission_settings` (`submission_setting_id`, `submission_id`, `locale`, `setting_name`, `setting_value`) VALUES
(2, 2, '', 'codecheckOptIn', '1'),
(3, 3, '', 'codecheckOptIn', '1'),
(4, 4, '', 'codecheckOptIn', '1'),
(5, 5, '', 'codecheckOptIn', '1'),
(7, 7, '', 'codecheckOptIn', '1'),
(8, 8, '', 'codecheckOptIn', '1'),
(9, 9, '', 'codecheckOptIn', '1'),
(10, 10, '', 'codecheckOptIn', '1');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `subscription_id` bigint NOT NULL,
  `journal_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `type_id` bigint NOT NULL,
  `date_start` date DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `status` smallint NOT NULL DEFAULT '1',
  `membership` varchar(40) DEFAULT NULL,
  `reference_number` varchar(40) DEFAULT NULL,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='A list of subscriptions, both institutional and individual, for journals that use subscription-based publishing.';

-- --------------------------------------------------------

--
-- Table structure for table `subscription_types`
--

CREATE TABLE `subscription_types` (
  `type_id` bigint NOT NULL,
  `journal_id` bigint NOT NULL,
  `cost` decimal(8,2) UNSIGNED NOT NULL,
  `currency_code_alpha` varchar(3) NOT NULL,
  `duration` smallint DEFAULT NULL,
  `format` smallint NOT NULL,
  `institutional` smallint NOT NULL DEFAULT '0',
  `membership` smallint NOT NULL DEFAULT '0',
  `disable_public_display` smallint NOT NULL,
  `seq` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Subscription types represent the kinds of subscriptions that a user or institution may have, such as an annual subscription or a discounted subscription.';

-- --------------------------------------------------------

--
-- Table structure for table `subscription_type_settings`
--

CREATE TABLE `subscription_type_settings` (
  `subscription_type_setting_id` bigint UNSIGNED NOT NULL,
  `type_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext,
  `setting_type` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about subscription types, including localized properties such as names.';

-- --------------------------------------------------------

--
-- Table structure for table `temporary_files`
--

CREATE TABLE `temporary_files` (
  `file_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `file_name` varchar(90) NOT NULL,
  `file_type` varchar(255) DEFAULT NULL,
  `file_size` bigint NOT NULL,
  `original_file_name` varchar(127) DEFAULT NULL,
  `date_uploaded` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Temporary files, e.g. where files are kept during an upload process before they are moved somewhere more appropriate.';

-- --------------------------------------------------------

--
-- Table structure for table `usage_stats_institution_temporary_records`
--

CREATE TABLE `usage_stats_institution_temporary_records` (
  `usage_stats_temp_institution_id` bigint UNSIGNED NOT NULL,
  `load_id` varchar(50) NOT NULL,
  `line_number` bigint NOT NULL,
  `institution_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Temporary stats for views and downloads from institutions based on visitor log records. Data in this table is provisional. See the metrics_* tables for compiled stats.';

-- --------------------------------------------------------

--
-- Table structure for table `usage_stats_total_temporary_records`
--

CREATE TABLE `usage_stats_total_temporary_records` (
  `usage_stats_temp_total_id` bigint UNSIGNED NOT NULL,
  `date` datetime NOT NULL,
  `ip` varchar(64) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `line_number` bigint NOT NULL,
  `canonical_url` varchar(255) NOT NULL,
  `issue_id` bigint DEFAULT NULL,
  `issue_galley_id` bigint DEFAULT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint DEFAULT NULL,
  `representation_id` bigint DEFAULT NULL,
  `submission_file_id` bigint UNSIGNED DEFAULT NULL,
  `assoc_type` bigint NOT NULL,
  `file_type` smallint DEFAULT NULL,
  `country` varchar(2) NOT NULL DEFAULT '',
  `region` varchar(3) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  `load_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Temporary stats totals based on visitor log records. Data in this table is provisional. See the metrics_* tables for compiled stats.';

-- --------------------------------------------------------

--
-- Table structure for table `usage_stats_unique_item_investigations_temporary_records`
--

CREATE TABLE `usage_stats_unique_item_investigations_temporary_records` (
  `usage_stats_temp_unique_item_id` bigint UNSIGNED NOT NULL,
  `date` datetime NOT NULL,
  `ip` varchar(64) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `line_number` bigint NOT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `representation_id` bigint DEFAULT NULL,
  `submission_file_id` bigint UNSIGNED DEFAULT NULL,
  `assoc_type` bigint NOT NULL,
  `file_type` smallint DEFAULT NULL,
  `country` varchar(2) NOT NULL DEFAULT '',
  `region` varchar(3) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  `load_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Temporary stats on unique downloads based on visitor log records. Data in this table is provisional. See the metrics_* tables for compiled stats.';

-- --------------------------------------------------------

--
-- Table structure for table `usage_stats_unique_item_requests_temporary_records`
--

CREATE TABLE `usage_stats_unique_item_requests_temporary_records` (
  `usage_stats_temp_item_id` bigint UNSIGNED NOT NULL,
  `date` datetime NOT NULL,
  `ip` varchar(64) NOT NULL,
  `user_agent` varchar(255) NOT NULL,
  `line_number` bigint NOT NULL,
  `context_id` bigint NOT NULL,
  `submission_id` bigint NOT NULL,
  `representation_id` bigint DEFAULT NULL,
  `submission_file_id` bigint UNSIGNED DEFAULT NULL,
  `assoc_type` bigint NOT NULL,
  `file_type` smallint DEFAULT NULL,
  `country` varchar(2) NOT NULL DEFAULT '',
  `region` varchar(3) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  `load_id` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Temporary stats on unique views based on visitor log records. Data in this table is provisional. See the metrics_* tables for compiled stats.';

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` bigint NOT NULL,
  `username` varchar(32) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `url` varchar(2047) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `mailing_address` varchar(255) DEFAULT NULL,
  `billing_address` varchar(255) DEFAULT NULL,
  `country` varchar(90) DEFAULT NULL,
  `locales` varchar(255) NOT NULL DEFAULT '[]',
  `gossip` text,
  `date_last_email` datetime DEFAULT NULL,
  `date_registered` datetime NOT NULL,
  `date_validated` datetime DEFAULT NULL,
  `date_last_login` datetime DEFAULT NULL,
  `must_change_password` smallint DEFAULT NULL,
  `auth_id` bigint DEFAULT NULL,
  `auth_str` varchar(255) DEFAULT NULL,
  `disabled` smallint NOT NULL DEFAULT '0',
  `disabled_reason` text,
  `inline_help` smallint DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='All registered users, including authentication data and profile data.';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `url`, `phone`, `mailing_address`, `billing_address`, `country`, `locales`, `gossip`, `date_last_email`, `date_registered`, `date_validated`, `date_last_login`, `must_change_password`, `auth_id`, `auth_str`, `disabled`, `disabled_reason`, `inline_help`, `remember_token`) VALUES
(1, 'admin', '$2y$12$AYrEfLLqR/KNV/YTH6hU3us7aNk01X.KiLk8yz2Mf8npycfwDC3xK', 'admin@example.com', NULL, NULL, NULL, NULL, NULL, '[]', NULL, NULL, '2026-02-12 07:54:57', NULL, '2026-02-26 14:02:21', NULL, NULL, NULL, 0, NULL, 1, '1qsLadHzNVww0h2RR9Hl92XGlzL7D6C3V2xWcY39ZOFK4xu0KiJpUVZhAgUE'),
(2, 'dnuest', '$2y$12$tWI5EuaXDbOcJaIvLbg9nOCSKYpAciOIq3buebQXqPCWMsqyxNbO6', 'dnuest@mailinator.com', '', '', '', NULL, '', '[]', NULL, NULL, '2026-02-12 09:00:05', NULL, '2026-02-26 02:00:48', 0, NULL, NULL, 0, NULL, 1, '68Hb5GIerHNPeyLcqZrkyb1QUTuLOoRLgFQyIvjVLqzEH5YKFfjEuAimsRbt'),
(3, 'seglen', '$2y$12$hjB7Xmv2mfvJzUBtZfrFDuzm7ZG0oam5r6z7ET0RXye.hSp3djtSm', 'seglen@mailinator.com', '', '', '', NULL, '', '[]', NULL, NULL, '2026-02-12 09:03:59', NULL, '2026-02-26 01:52:28', 0, NULL, NULL, 0, NULL, 1, 'yuSUcx4bpxEnXEUQNxOrH91liKrmG7CXgYC8q7X7SO3hEstzrHR7ybAxUZ5Z'),
(4, 'fostermann', '$2y$12$oWY81bUPVnrIXtG0U61nz.fO8p/nR6mCaqz8hbL7URiot15jIatA6', 'fostermann@mailinator.com', '', '', '', NULL, '', '[]', NULL, NULL, '2026-02-12 09:05:29', NULL, '2026-02-26 02:11:54', 0, NULL, NULL, 0, NULL, 1, '8ZNJDW6lnOSLE25NPyhXOweZdmkHVkksSt9Uea5miVbvjEB3chgzvoqaaRIL'),
(5, 'jmanager', '$2y$12$MOedT/6Crk61mLTauMg7e.oaJYAlChXTNUse.ul7ahaaQ1RIhKWFW', 'jmanager@mailinator.com', '', '', '', NULL, '', '[]', NULL, NULL, '2026-02-12 09:07:48', NULL, '2026-02-26 02:19:01', 0, NULL, NULL, 0, NULL, 1, 'dmAs9l278hBRyLz8VXsbJYRhE7FinmRI5pCUbikUlT89RoFApi2ms7p4RJ4I'),
(6, 'rreviewer', '$2y$12$tWDpcrUr/EKX/UkR.xDJgu0.qZQzcYxeW5dF3lv4p.mt2EeZrBfLu', 'rreviewer@mailinator.com', '', '', '', NULL, '', '[]', NULL, NULL, '2026-02-12 09:09:36', NULL, NULL, 0, NULL, NULL, 0, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_groups`
--

CREATE TABLE `user_groups` (
  `user_group_id` bigint NOT NULL,
  `context_id` bigint DEFAULT NULL,
  `role_id` bigint NOT NULL,
  `is_default` smallint NOT NULL DEFAULT '0',
  `show_title` smallint NOT NULL DEFAULT '1',
  `permit_self_registration` smallint NOT NULL DEFAULT '0',
  `permit_metadata_edit` smallint NOT NULL DEFAULT '0',
  `permit_settings` smallint NOT NULL DEFAULT '0',
  `masthead` smallint NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='All defined user roles in a context, such as Author, Reviewer, Section Editor and Journal Manager.';

--
-- Dumping data for table `user_groups`
--

INSERT INTO `user_groups` (`user_group_id`, `context_id`, `role_id`, `is_default`, `show_title`, `permit_self_registration`, `permit_metadata_edit`, `permit_settings`, `masthead`) VALUES
(1, NULL, 1, 1, 1, 0, 0, 1, 0),
(2, 1, 16, 1, 1, 0, 1, 1, 0),
(3, 1, 16, 1, 1, 0, 1, 1, 1),
(4, 1, 16, 1, 1, 0, 1, 1, 0),
(5, 1, 17, 1, 1, 0, 1, 0, 1),
(6, 1, 17, 1, 1, 0, 0, 0, 0),
(7, 1, 4097, 1, 1, 0, 0, 0, 0),
(8, 1, 4097, 1, 1, 0, 0, 0, 0),
(9, 1, 4097, 1, 1, 0, 0, 0, 0),
(10, 1, 4097, 1, 1, 0, 0, 0, 0),
(11, 1, 4097, 1, 1, 0, 0, 0, 0),
(12, 1, 4097, 1, 1, 0, 0, 0, 0),
(13, 1, 4097, 1, 1, 0, 0, 0, 0),
(14, 1, 65536, 1, 1, 1, 0, 0, 0),
(15, 1, 65536, 1, 1, 0, 0, 0, 0),
(16, 1, 4096, 1, 1, 1, 0, 0, 1),
(17, 1, 1048576, 1, 1, 1, 0, 0, 0),
(18, 1, 2097152, 1, 1, 0, 0, 0, 0),
(19, 1, 4097, 1, 1, 0, 0, 0, 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_group_settings`
--

CREATE TABLE `user_group_settings` (
  `user_group_setting_id` bigint UNSIGNED NOT NULL,
  `user_group_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about user groups, including localized properties such as the name.';

--
-- Dumping data for table `user_group_settings`
--

INSERT INTO `user_group_settings` (`user_group_setting_id`, `user_group_id`, `locale`, `setting_name`, `setting_value`) VALUES
(1, 2, '', 'nameLocaleKey', 'default.groups.name.manager'),
(2, 2, '', 'abbrevLocaleKey', 'default.groups.abbrev.manager'),
(3, 2, 'de', 'name', 'Zeitschriftenverwalter/in'),
(4, 2, 'de', 'abbrev', 'ZV'),
(5, 2, 'en', 'name', 'Journal manager'),
(6, 2, 'en', 'abbrev', 'JM'),
(7, 3, '', 'nameLocaleKey', 'default.groups.name.editor'),
(8, 3, '', 'abbrevLocaleKey', 'default.groups.abbrev.editor'),
(9, 3, 'de', 'name', 'Zeitschriftenredakteur/in'),
(10, 3, 'de', 'abbrev', 'ZR'),
(11, 3, 'en', 'name', 'Journal editor'),
(12, 3, 'en', 'abbrev', 'JE'),
(13, 4, '', 'nameLocaleKey', 'default.groups.name.productionEditor'),
(14, 4, '', 'abbrevLocaleKey', 'default.groups.abbrev.productionEditor'),
(15, 4, 'de', 'name', 'Produktionsredakteur/in'),
(16, 4, 'de', 'abbrev', 'ProdR'),
(17, 4, 'en', 'name', 'Production editor'),
(18, 4, 'en', 'abbrev', 'ProdE'),
(19, 5, '', 'nameLocaleKey', 'default.groups.name.sectionEditor'),
(20, 5, '', 'abbrevLocaleKey', 'default.groups.abbrev.sectionEditor'),
(21, 5, 'de', 'name', 'Rubrikredakteur/in'),
(22, 5, 'de', 'abbrev', 'RR'),
(23, 5, 'en', 'name', 'Section editor'),
(24, 5, 'en', 'abbrev', 'SecE'),
(25, 6, '', 'nameLocaleKey', 'default.groups.name.guestEditor'),
(26, 6, '', 'abbrevLocaleKey', 'default.groups.abbrev.guestEditor'),
(27, 6, 'de', 'name', 'Gastredakteur/in'),
(28, 6, 'de', 'abbrev', 'GR'),
(29, 6, 'en', 'name', 'Guest editor'),
(30, 6, 'en', 'abbrev', 'GE'),
(31, 7, '', 'nameLocaleKey', 'default.groups.name.copyeditor'),
(32, 7, '', 'abbrevLocaleKey', 'default.groups.abbrev.copyeditor'),
(33, 7, 'de', 'name', 'Lektor/in'),
(34, 7, 'de', 'abbrev', 'CE'),
(35, 7, 'en', 'name', 'Copyeditor'),
(36, 7, 'en', 'abbrev', 'CE'),
(37, 8, '', 'nameLocaleKey', 'default.groups.name.designer'),
(38, 8, '', 'abbrevLocaleKey', 'default.groups.abbrev.designer'),
(39, 8, 'de', 'name', 'Designer/in'),
(40, 8, 'de', 'abbrev', 'Design'),
(41, 8, 'en', 'name', 'Designer'),
(42, 8, 'en', 'abbrev', 'Design'),
(43, 9, '', 'nameLocaleKey', 'default.groups.name.funding'),
(44, 9, '', 'abbrevLocaleKey', 'default.groups.abbrev.funding'),
(45, 9, 'de', 'name', 'Finanzierungskoordinator/in'),
(46, 9, 'de', 'abbrev', 'FC'),
(47, 9, 'en', 'name', 'Funding coordinator'),
(48, 9, 'en', 'abbrev', 'FC'),
(49, 10, '', 'nameLocaleKey', 'default.groups.name.indexer'),
(50, 10, '', 'abbrevLocaleKey', 'default.groups.abbrev.indexer'),
(51, 10, 'de', 'name', 'Indizierer/in'),
(52, 10, 'de', 'abbrev', 'IND'),
(53, 10, 'en', 'name', 'Indexer'),
(54, 10, 'en', 'abbrev', 'IND'),
(55, 11, '', 'nameLocaleKey', 'default.groups.name.layoutEditor'),
(56, 11, '', 'abbrevLocaleKey', 'default.groups.abbrev.layoutEditor'),
(57, 11, 'de', 'name', 'Layout-Leiter/in'),
(58, 11, 'de', 'abbrev', 'LE'),
(59, 11, 'en', 'name', 'Layout Editor'),
(60, 11, 'en', 'abbrev', 'LE'),
(61, 12, '', 'nameLocaleKey', 'default.groups.name.marketing'),
(62, 12, '', 'abbrevLocaleKey', 'default.groups.abbrev.marketing'),
(63, 12, 'de', 'name', 'Marketing- und Vertriebkoordinator/in'),
(64, 12, 'de', 'abbrev', 'MS'),
(65, 12, 'en', 'name', 'Marketing and sales coordinator'),
(66, 12, 'en', 'abbrev', 'MS'),
(67, 13, '', 'nameLocaleKey', 'default.groups.name.proofreader'),
(68, 13, '', 'abbrevLocaleKey', 'default.groups.abbrev.proofreader'),
(69, 13, 'de', 'name', 'Korrektor/in'),
(70, 13, 'de', 'abbrev', 'PR'),
(71, 13, 'en', 'name', 'Proofreader'),
(72, 13, 'en', 'abbrev', 'PR'),
(73, 14, '', 'nameLocaleKey', 'default.groups.name.author'),
(74, 14, '', 'abbrevLocaleKey', 'default.groups.abbrev.author'),
(75, 14, 'de', 'name', 'Autor/in'),
(76, 14, 'de', 'abbrev', 'AU'),
(77, 14, 'en', 'name', 'Author'),
(78, 14, 'en', 'abbrev', 'AU'),
(79, 15, '', 'nameLocaleKey', 'default.groups.name.translator'),
(80, 15, '', 'abbrevLocaleKey', 'default.groups.abbrev.translator'),
(81, 15, 'de', 'name', 'Übersetzer/in'),
(82, 15, 'de', 'abbrev', 'Trans'),
(83, 15, 'en', 'name', 'Translator'),
(84, 15, 'en', 'abbrev', 'Trans'),
(85, 16, '', 'nameLocaleKey', 'default.groups.name.externalReviewer'),
(86, 16, '', 'abbrevLocaleKey', 'default.groups.abbrev.externalReviewer'),
(87, 16, 'de', 'name', 'externe/r Gutachter/in'),
(88, 16, 'de', 'abbrev', 'G'),
(89, 16, 'en', 'name', 'Reviewer'),
(90, 16, 'en', 'abbrev', 'R'),
(91, 17, '', 'nameLocaleKey', 'default.groups.name.reader'),
(92, 17, '', 'abbrevLocaleKey', 'default.groups.abbrev.reader'),
(93, 17, 'de', 'name', 'Leser/in'),
(94, 17, 'de', 'abbrev', 'Lesen'),
(95, 17, 'en', 'name', 'Reader'),
(96, 17, 'en', 'abbrev', 'Read'),
(97, 18, '', 'nameLocaleKey', 'default.groups.name.subscriptionManager'),
(98, 18, '', 'abbrevLocaleKey', 'default.groups.abbrev.subscriptionManager'),
(99, 18, 'de', 'name', 'Abonnementverwalter/in'),
(100, 18, 'de', 'abbrev', 'AV'),
(101, 18, 'en', 'name', 'Subscription Manager'),
(102, 18, 'en', 'abbrev', 'SubM'),
(103, 19, '', 'nameLocaleKey', 'default.groups.name.editorialBoardMember'),
(104, 19, '', 'abbrevLocaleKey', 'default.groups.abbrev.editorialBoardMember'),
(105, 19, 'de', 'name', 'Redaktionsmitglied'),
(106, 19, 'de', 'abbrev', 'EBM'),
(107, 19, 'en', 'name', 'Editorial Board Member'),
(108, 19, 'en', 'abbrev', 'EBM');

-- --------------------------------------------------------

--
-- Table structure for table `user_group_stage`
--

CREATE TABLE `user_group_stage` (
  `user_group_stage_id` bigint UNSIGNED NOT NULL,
  `context_id` bigint NOT NULL,
  `user_group_id` bigint NOT NULL,
  `stage_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Which stages of the editorial workflow the user_groups can access.';

--
-- Dumping data for table `user_group_stage`
--

INSERT INTO `user_group_stage` (`user_group_stage_id`, `context_id`, `user_group_id`, `stage_id`) VALUES
(1, 1, 3, 1),
(2, 1, 3, 3),
(3, 1, 3, 4),
(4, 1, 3, 5),
(5, 1, 4, 4),
(6, 1, 4, 5),
(7, 1, 5, 1),
(8, 1, 5, 3),
(9, 1, 5, 4),
(10, 1, 5, 5),
(11, 1, 6, 1),
(12, 1, 6, 3),
(13, 1, 6, 4),
(14, 1, 6, 5),
(15, 1, 7, 4),
(16, 1, 8, 5),
(17, 1, 9, 1),
(18, 1, 9, 3),
(19, 1, 10, 5),
(20, 1, 11, 5),
(21, 1, 12, 4),
(22, 1, 13, 5),
(23, 1, 14, 1),
(24, 1, 14, 3),
(25, 1, 14, 4),
(26, 1, 14, 5),
(27, 1, 15, 1),
(28, 1, 15, 3),
(29, 1, 15, 4),
(30, 1, 15, 5),
(31, 1, 16, 3);

-- --------------------------------------------------------

--
-- Table structure for table `user_interests`
--

CREATE TABLE `user_interests` (
  `user_interest_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint NOT NULL,
  `controlled_vocab_entry_id` bigint NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Associates users with user interests (which are stored in the controlled vocabulary tables).';

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `user_setting_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint NOT NULL,
  `locale` varchar(28) NOT NULL DEFAULT '',
  `setting_name` varchar(255) NOT NULL,
  `setting_value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='More data about users, including localized properties like their name and affiliation.';

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`user_setting_id`, `user_id`, `locale`, `setting_name`, `setting_value`) VALUES
(1, 1, 'en', 'familyName', 'admin'),
(2, 1, 'en', 'givenName', 'admin'),
(3, 2, 'en', 'affiliation', ''),
(4, 2, 'en', 'biography', ''),
(5, 2, 'en', 'familyName', 'Nüst'),
(6, 2, 'en', 'givenName', 'Daniel'),
(7, 2, 'en', 'preferredPublicName', ''),
(8, 2, 'en', 'signature', ''),
(9, 3, 'en', 'affiliation', ''),
(10, 3, 'en', 'biography', ''),
(11, 3, 'en', 'familyName', 'Eglen'),
(12, 3, 'en', 'givenName', 'Stephen'),
(13, 3, 'en', 'preferredPublicName', ''),
(14, 3, 'en', 'signature', ''),
(15, 4, 'en', 'affiliation', ''),
(16, 4, 'en', 'biography', ''),
(17, 4, 'en', 'familyName', 'Ostermann'),
(18, 4, 'en', 'givenName', 'Frank'),
(19, 4, 'en', 'preferredPublicName', ''),
(20, 4, 'en', 'signature', ''),
(21, 5, 'en', 'affiliation', ''),
(22, 5, 'en', 'biography', ''),
(23, 5, 'en', 'familyName', 'Manager'),
(24, 5, 'en', 'givenName', 'Jane'),
(25, 5, 'en', 'preferredPublicName', ''),
(26, 5, 'en', 'signature', ''),
(27, 6, 'en', 'affiliation', ''),
(28, 6, 'en', 'biography', ''),
(29, 6, 'en', 'familyName', 'Reviewer'),
(30, 6, 'en', 'givenName', 'Rosa'),
(31, 6, 'en', 'preferredPublicName', ''),
(32, 6, 'en', 'signature', '');

-- --------------------------------------------------------

--
-- Table structure for table `user_user_groups`
--

CREATE TABLE `user_user_groups` (
  `user_user_group_id` bigint UNSIGNED NOT NULL,
  `user_group_id` bigint NOT NULL,
  `user_id` bigint NOT NULL,
  `date_start` datetime DEFAULT NULL,
  `date_end` datetime DEFAULT NULL,
  `masthead` smallint DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Maps users to their assigned user_groups.';

--
-- Dumping data for table `user_user_groups`
--

INSERT INTO `user_user_groups` (`user_user_group_id`, `user_group_id`, `user_id`, `date_start`, `date_end`, `masthead`) VALUES
(1, 1, 1, '2026-02-12 07:54:57', NULL, NULL),
(2, 2, 1, NULL, NULL, NULL),
(3, 14, 2, '2026-02-12 09:02:16', NULL, 0),
(4, 14, 3, '2026-02-12 09:04:26', NULL, 0),
(5, 14, 4, '2026-02-12 09:05:55', NULL, 0),
(6, 2, 5, '2026-02-12 09:08:42', NULL, 0),
(7, 3, 5, '2026-02-12 09:08:42', NULL, 0),
(8, 16, 6, '2026-02-12 09:10:36', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `versions`
--

CREATE TABLE `versions` (
  `version_id` bigint UNSIGNED NOT NULL,
  `major` int NOT NULL DEFAULT '0' COMMENT 'Major component of version number, e.g. the 2 in OJS 2.3.8-0',
  `minor` int NOT NULL DEFAULT '0' COMMENT 'Minor component of version number, e.g. the 3 in OJS 2.3.8-0',
  `revision` int NOT NULL DEFAULT '0' COMMENT 'Revision component of version number, e.g. the 8 in OJS 2.3.8-0',
  `build` int NOT NULL DEFAULT '0' COMMENT 'Build component of version number, e.g. the 0 in OJS 2.3.8-0',
  `date_installed` datetime NOT NULL,
  `current` smallint NOT NULL DEFAULT '0' COMMENT '1 iff the version entry being described is currently active. This permits the table to store past installation history for forensic purposes.',
  `product_type` varchar(30) DEFAULT NULL COMMENT 'Describes the type of product this row describes, e.g. "plugins.generic" (for a generic plugin) or "core" for the application itself',
  `product` varchar(30) DEFAULT NULL COMMENT 'Uniquely identifies the product this version row describes, e.g. "ojs2" for OJS 2.x, "languageToggle" for the language toggle block plugin, etc.',
  `product_class_name` varchar(80) DEFAULT NULL COMMENT 'Specifies the class name associated with this product, for plugins, or the empty string where not applicable.',
  `lazy_load` smallint NOT NULL DEFAULT '0' COMMENT '1 iff the row describes a lazy-load plugin; 0 otherwise',
  `sitewide` smallint NOT NULL DEFAULT '0' COMMENT '1 iff the row describes a site-wide plugin; 0 otherwise'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COMMENT='Describes the installation and upgrade version history for the application and all installed plugins.';

--
-- Dumping data for table `versions`
--

INSERT INTO `versions` (`version_id`, `major`, `minor`, `revision`, `build`, `date_installed`, `current`, `product_type`, `product`, `product_class_name`, `lazy_load`, `sitewide`) VALUES
(1, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.metadata', 'dc11', '', 0, 0),
(2, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.blocks', 'developedBy', 'DevelopedByBlockPlugin', 1, 0),
(3, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.blocks', 'information', 'InformationBlockPlugin', 1, 0),
(4, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.blocks', 'makeSubmission', 'MakeSubmissionBlockPlugin', 1, 0),
(5, 1, 1, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.blocks', 'subscription', 'SubscriptionBlockPlugin', 1, 0),
(6, 1, 0, 1, 0, '2026-02-12 07:54:57', 1, 'plugins.blocks', 'browse', 'BrowseBlockPlugin', 1, 0),
(7, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.blocks', 'languageToggle', 'LanguageToggleBlockPlugin', 1, 0),
(8, 0, 1, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'citationStyleLanguage', 'CitationStyleLanguagePlugin', 1, 0),
(9, 1, 2, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'customBlockManager', 'CustomBlockManagerPlugin', 1, 0),
(10, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'usageEvent', '', 0, 0),
(11, 1, 2, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'credit', 'CreditPlugin', 1, 0),
(12, 1, 0, 1, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'lensGalley', 'LensGalleyPlugin', 1, 0),
(13, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'tinymce', 'TinyMCEPlugin', 1, 0),
(14, 1, 1, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'googleScholar', 'GoogleScholarPlugin', 1, 0),
(15, 1, 2, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'staticPages', 'StaticPagesPlugin', 1, 0),
(16, 0, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'codecheck', 'CodecheckPlugin', 0, 0),
(17, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'driver', 'DRIVERPlugin', 1, 0),
(18, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'announcementFeed', 'AnnouncementFeedPlugin', 1, 0),
(19, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'dublinCoreMeta', 'DublinCoreMetaPlugin', 1, 0),
(20, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'webFeed', 'WebFeedPlugin', 1, 0),
(21, 3, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'crossref', 'CrossrefPlugin', 0, 0),
(22, 1, 2, 1, 1, '2026-02-12 07:54:57', 1, 'plugins.generic', 'pflPlugin', 'PflPlugin', 1, 0),
(23, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'htmlArticleGalley', 'HtmlArticleGalleyPlugin', 1, 0),
(24, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'googleAnalytics', 'GoogleAnalyticsPlugin', 1, 0),
(25, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'recommendBySimilarity', 'RecommendBySimilarityPlugin', 1, 1),
(26, 1, 0, 1, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'pdfJsViewer', 'PdfJsViewerPlugin', 1, 0),
(27, 2, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'datacite', 'DatacitePlugin', 0, 0),
(28, 1, 0, 8, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'jatsTemplate', 'JatsTemplatePlugin', 1, 0),
(29, 1, 0, 0, 1, '2026-02-12 07:54:57', 1, 'plugins.generic', 'recommendByAuthor', 'RecommendByAuthorPlugin', 1, 1),
(30, 1, 0, 1, 0, '2026-02-12 07:54:57', 1, 'plugins.generic', 'geoMetadata', 'GeoMetadataPlugin', 1, 0),
(31, 1, 1, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.importexport', 'doaj', '', 0, 0),
(32, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.importexport', 'native', '', 0, 0),
(33, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.importexport', 'users', '', 0, 0),
(34, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.importexport', 'pubmed', '', 0, 0),
(35, 1, 0, 6, 1, '2026-02-12 07:54:57', 1, 'plugins.oaiMetadataFormats', 'oaiJats', '', 0, 0),
(36, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.oaiMetadataFormats', 'rfc1807', '', 0, 0),
(37, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.oaiMetadataFormats', 'marc', '', 0, 0),
(38, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.oaiMetadataFormats', 'marcxml', '', 0, 0),
(39, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.oaiMetadataFormats', 'dc', '', 0, 0),
(40, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.paymethod', 'manual', '', 0, 0),
(41, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.paymethod', 'paypal', '', 0, 0),
(42, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.pubIds', 'urn', 'URNPubIdPlugin', 1, 0),
(43, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.reports', 'articles', '', 0, 0),
(44, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.reports', 'subscriptions', '', 0, 0),
(45, 2, 0, 1, 0, '2026-02-12 07:54:57', 1, 'plugins.reports', 'reviewReport', '', 0, 0),
(46, 1, 1, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.reports', 'counterReport', '', 0, 0),
(47, 1, 0, 0, 0, '2026-02-12 07:54:57', 1, 'plugins.themes', 'default', 'DefaultThemePlugin', 1, 0),
(48, 3, 5, 0, 3, '2026-02-12 07:54:52', 1, 'core', 'ojs2', '', 0, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `announcements_type_id` (`type_id`),
  ADD KEY `announcements_assoc` (`assoc_type`,`assoc_id`);

--
-- Indexes for table `announcement_settings`
--
ALTER TABLE `announcement_settings`
  ADD PRIMARY KEY (`announcement_setting_id`),
  ADD UNIQUE KEY `announcement_settings_unique` (`announcement_id`,`locale`,`setting_name`),
  ADD KEY `announcement_settings_announcement_id` (`announcement_id`);

--
-- Indexes for table `announcement_types`
--
ALTER TABLE `announcement_types`
  ADD PRIMARY KEY (`type_id`),
  ADD KEY `announcement_types_context_id` (`context_id`);

--
-- Indexes for table `announcement_type_settings`
--
ALTER TABLE `announcement_type_settings`
  ADD PRIMARY KEY (`announcement_type_setting_id`),
  ADD UNIQUE KEY `announcement_type_settings_unique` (`type_id`,`locale`,`setting_name`),
  ADD KEY `announcement_type_settings_type_id` (`type_id`);

--
-- Indexes for table `authors`
--
ALTER TABLE `authors`
  ADD PRIMARY KEY (`author_id`),
  ADD KEY `authors_user_group_id` (`user_group_id`),
  ADD KEY `authors_publication_id` (`publication_id`);

--
-- Indexes for table `author_affiliations`
--
ALTER TABLE `author_affiliations`
  ADD PRIMARY KEY (`author_affiliation_id`),
  ADD KEY `author_affiliations_ror` (`ror`),
  ADD KEY `author_affiliations_author_id_foreign` (`author_id`);

--
-- Indexes for table `author_affiliation_settings`
--
ALTER TABLE `author_affiliation_settings`
  ADD PRIMARY KEY (`author_affiliation_setting_id`),
  ADD UNIQUE KEY `author_affiliation_settings_unique` (`author_affiliation_id`,`locale`,`setting_name`);

--
-- Indexes for table `author_settings`
--
ALTER TABLE `author_settings`
  ADD PRIMARY KEY (`author_setting_id`),
  ADD UNIQUE KEY `author_settings_unique` (`author_id`,`locale`,`setting_name`),
  ADD KEY `author_settings_author_id` (`author_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `category_path` (`context_id`,`path`),
  ADD KEY `category_context_id` (`context_id`),
  ADD KEY `category_context_parent_id` (`context_id`,`parent_id`),
  ADD KEY `category_parent_id` (`parent_id`);

--
-- Indexes for table `category_settings`
--
ALTER TABLE `category_settings`
  ADD PRIMARY KEY (`category_setting_id`),
  ADD UNIQUE KEY `category_settings_unique` (`category_id`,`locale`,`setting_name`),
  ADD KEY `category_settings_category_id` (`category_id`);

--
-- Indexes for table `citations`
--
ALTER TABLE `citations`
  ADD PRIMARY KEY (`citation_id`),
  ADD UNIQUE KEY `citations_publication_seq` (`publication_id`,`seq`),
  ADD KEY `citations_publication` (`publication_id`);

--
-- Indexes for table `citation_settings`
--
ALTER TABLE `citation_settings`
  ADD PRIMARY KEY (`citation_setting_id`),
  ADD UNIQUE KEY `citation_settings_unique` (`citation_id`,`locale`,`setting_name`),
  ADD KEY `citation_settings_citation_id` (`citation_id`);

--
-- Indexes for table `codecheck_metadata`
--
ALTER TABLE `codecheck_metadata`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `codecheck_metadata_submission_id_index` (`submission_id`);

--
-- Indexes for table `completed_payments`
--
ALTER TABLE `completed_payments`
  ADD PRIMARY KEY (`completed_payment_id`),
  ADD KEY `completed_payments_context_id` (`context_id`),
  ADD KEY `completed_payments_user_id` (`user_id`);

--
-- Indexes for table `controlled_vocabs`
--
ALTER TABLE `controlled_vocabs`
  ADD PRIMARY KEY (`controlled_vocab_id`),
  ADD UNIQUE KEY `controlled_vocab_symbolic` (`symbolic`,`assoc_type`,`assoc_id`);

--
-- Indexes for table `controlled_vocab_entries`
--
ALTER TABLE `controlled_vocab_entries`
  ADD PRIMARY KEY (`controlled_vocab_entry_id`),
  ADD KEY `controlled_vocab_entries_controlled_vocab_id` (`controlled_vocab_id`),
  ADD KEY `controlled_vocab_entries_cv_id` (`controlled_vocab_id`,`seq`);

--
-- Indexes for table `controlled_vocab_entry_settings`
--
ALTER TABLE `controlled_vocab_entry_settings`
  ADD PRIMARY KEY (`controlled_vocab_entry_setting_id`),
  ADD UNIQUE KEY `c_v_e_s_pkey` (`controlled_vocab_entry_id`,`locale`,`setting_name`),
  ADD KEY `c_v_e_s_entry_id` (`controlled_vocab_entry_id`);

--
-- Indexes for table `custom_issue_orders`
--
ALTER TABLE `custom_issue_orders`
  ADD PRIMARY KEY (`custom_issue_order_id`),
  ADD UNIQUE KEY `custom_issue_orders_unique` (`issue_id`),
  ADD KEY `custom_issue_orders_issue_id` (`issue_id`),
  ADD KEY `custom_issue_orders_journal_id` (`journal_id`);

--
-- Indexes for table `custom_section_orders`
--
ALTER TABLE `custom_section_orders`
  ADD PRIMARY KEY (`custom_section_order_id`),
  ADD UNIQUE KEY `custom_section_orders_unique` (`issue_id`,`section_id`),
  ADD KEY `custom_section_orders_issue_id` (`issue_id`),
  ADD KEY `custom_section_orders_section_id` (`section_id`);

--
-- Indexes for table `data_object_tombstones`
--
ALTER TABLE `data_object_tombstones`
  ADD PRIMARY KEY (`tombstone_id`),
  ADD KEY `data_object_tombstones_data_object_id` (`data_object_id`);

--
-- Indexes for table `data_object_tombstone_oai_set_objects`
--
ALTER TABLE `data_object_tombstone_oai_set_objects`
  ADD PRIMARY KEY (`object_id`),
  ADD KEY `data_object_tombstone_oai_set_objects_tombstone_id` (`tombstone_id`);

--
-- Indexes for table `data_object_tombstone_settings`
--
ALTER TABLE `data_object_tombstone_settings`
  ADD PRIMARY KEY (`tombstone_setting_id`),
  ADD UNIQUE KEY `data_object_tombstone_settings_unique` (`tombstone_id`,`locale`,`setting_name`),
  ADD KEY `data_object_tombstone_settings_tombstone_id` (`tombstone_id`);

--
-- Indexes for table `dois`
--
ALTER TABLE `dois`
  ADD PRIMARY KEY (`doi_id`),
  ADD KEY `dois_context_id` (`context_id`);

--
-- Indexes for table `doi_settings`
--
ALTER TABLE `doi_settings`
  ADD PRIMARY KEY (`doi_setting_id`),
  ADD UNIQUE KEY `doi_settings_unique` (`doi_id`,`locale`,`setting_name`),
  ADD KEY `doi_settings_doi_id` (`doi_id`);

--
-- Indexes for table `edit_decisions`
--
ALTER TABLE `edit_decisions`
  ADD PRIMARY KEY (`edit_decision_id`),
  ADD KEY `edit_decisions_submission_id` (`submission_id`),
  ADD KEY `edit_decisions_editor_id` (`editor_id`),
  ADD KEY `edit_decisions_review_round_id` (`review_round_id`);

--
-- Indexes for table `email_log`
--
ALTER TABLE `email_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `email_log_sender_id` (`sender_id`),
  ADD KEY `email_log_assoc` (`assoc_type`,`assoc_id`);

--
-- Indexes for table `email_log_users`
--
ALTER TABLE `email_log_users`
  ADD PRIMARY KEY (`email_log_user_id`),
  ADD UNIQUE KEY `email_log_user_id` (`email_log_id`,`user_id`),
  ADD KEY `email_log_users_email_log_id` (`email_log_id`),
  ADD KEY `email_log_users_user_id` (`user_id`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`email_id`),
  ADD UNIQUE KEY `email_templates_email_key` (`email_key`,`context_id`),
  ADD KEY `email_templates_context_id` (`context_id`),
  ADD KEY `email_templates_alternate_to` (`alternate_to`);

--
-- Indexes for table `email_templates_default_data`
--
ALTER TABLE `email_templates_default_data`
  ADD PRIMARY KEY (`email_templates_default_data_id`),
  ADD UNIQUE KEY `email_templates_default_data_unique` (`email_key`,`locale`);

--
-- Indexes for table `email_templates_settings`
--
ALTER TABLE `email_templates_settings`
  ADD PRIMARY KEY (`email_template_setting_id`),
  ADD UNIQUE KEY `email_templates_settings_unique` (`email_id`,`locale`,`setting_name`),
  ADD KEY `email_templates_settings_email_id` (`email_id`);

--
-- Indexes for table `event_log`
--
ALTER TABLE `event_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `event_log_user_id` (`user_id`),
  ADD KEY `event_log_assoc` (`assoc_type`,`assoc_id`);

--
-- Indexes for table `event_log_settings`
--
ALTER TABLE `event_log_settings`
  ADD PRIMARY KEY (`event_log_setting_id`),
  ADD UNIQUE KEY `event_log_settings_unique` (`log_id`,`setting_name`,`locale`),
  ADD KEY `event_log_settings_log_id` (`log_id`),
  ADD KEY `event_log_settings_name_value` (`setting_name`(50),`setting_value`(150));

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`file_id`);

--
-- Indexes for table `filters`
--
ALTER TABLE `filters`
  ADD PRIMARY KEY (`filter_id`),
  ADD KEY `filters_filter_group_id` (`filter_group_id`),
  ADD KEY `filters_context_id` (`context_id`),
  ADD KEY `filters_parent_filter_id` (`parent_filter_id`);

--
-- Indexes for table `filter_groups`
--
ALTER TABLE `filter_groups`
  ADD PRIMARY KEY (`filter_group_id`),
  ADD UNIQUE KEY `filter_groups_symbolic` (`symbolic`);

--
-- Indexes for table `filter_settings`
--
ALTER TABLE `filter_settings`
  ADD PRIMARY KEY (`filter_setting_id`),
  ADD UNIQUE KEY `filter_settings_unique` (`filter_id`,`locale`,`setting_name`),
  ADD KEY `filter_settings_id` (`filter_id`);

--
-- Indexes for table `genres`
--
ALTER TABLE `genres`
  ADD PRIMARY KEY (`genre_id`),
  ADD KEY `genres_context_id` (`context_id`);

--
-- Indexes for table `genre_settings`
--
ALTER TABLE `genre_settings`
  ADD PRIMARY KEY (`genre_setting_id`),
  ADD UNIQUE KEY `genre_settings_unique` (`genre_id`,`locale`,`setting_name`),
  ADD KEY `genre_settings_genre_id` (`genre_id`);

--
-- Indexes for table `highlights`
--
ALTER TABLE `highlights`
  ADD PRIMARY KEY (`highlight_id`),
  ADD KEY `highlights_context_id` (`context_id`);

--
-- Indexes for table `highlight_settings`
--
ALTER TABLE `highlight_settings`
  ADD PRIMARY KEY (`highlight_setting_id`),
  ADD UNIQUE KEY `highlight_settings_unique` (`highlight_id`,`locale`,`setting_name`),
  ADD KEY `highlight_settings_highlight_id` (`highlight_id`);

--
-- Indexes for table `institutional_subscriptions`
--
ALTER TABLE `institutional_subscriptions`
  ADD PRIMARY KEY (`institutional_subscription_id`),
  ADD KEY `institutional_subscriptions_subscription_id` (`subscription_id`),
  ADD KEY `institutional_subscriptions_institution_id` (`institution_id`),
  ADD KEY `institutional_subscriptions_domain` (`domain`);

--
-- Indexes for table `institutions`
--
ALTER TABLE `institutions`
  ADD PRIMARY KEY (`institution_id`),
  ADD KEY `institutions_context_id` (`context_id`);

--
-- Indexes for table `institution_ip`
--
ALTER TABLE `institution_ip`
  ADD PRIMARY KEY (`institution_ip_id`),
  ADD KEY `institution_ip_institution_id` (`institution_id`),
  ADD KEY `institution_ip_start` (`ip_start`),
  ADD KEY `institution_ip_end` (`ip_end`);

--
-- Indexes for table `institution_settings`
--
ALTER TABLE `institution_settings`
  ADD PRIMARY KEY (`institution_setting_id`),
  ADD UNIQUE KEY `institution_settings_unique` (`institution_id`,`locale`,`setting_name`),
  ADD KEY `institution_settings_institution_id` (`institution_id`);

--
-- Indexes for table `invitations`
--
ALTER TABLE `invitations`
  ADD PRIMARY KEY (`invitation_id`),
  ADD KEY `invitations_user_id` (`user_id`),
  ADD KEY `invitations_inviter_id` (`inviter_id`),
  ADD KEY `invitations_context_id` (`context_id`),
  ADD KEY `invitations_status_context_id_user_id_type_index` (`status`,`context_id`,`user_id`,`type`),
  ADD KEY `invitations_expiry_date_index` (`expiry_date`);

--
-- Indexes for table `issues`
--
ALTER TABLE `issues`
  ADD PRIMARY KEY (`issue_id`),
  ADD KEY `issues_journal_id` (`journal_id`),
  ADD KEY `issues_doi_id` (`doi_id`),
  ADD KEY `issues_url_path` (`url_path`);

--
-- Indexes for table `issue_files`
--
ALTER TABLE `issue_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `issue_files_issue_id` (`issue_id`);

--
-- Indexes for table `issue_galleys`
--
ALTER TABLE `issue_galleys`
  ADD PRIMARY KEY (`galley_id`),
  ADD KEY `issue_galleys_issue_id` (`issue_id`),
  ADD KEY `issue_galleys_file_id` (`file_id`),
  ADD KEY `issue_galleys_url_path` (`url_path`);

--
-- Indexes for table `issue_galley_settings`
--
ALTER TABLE `issue_galley_settings`
  ADD PRIMARY KEY (`issue_galley_setting_id`),
  ADD UNIQUE KEY `issue_galley_settings_unique` (`galley_id`,`locale`,`setting_name`),
  ADD KEY `issue_galley_settings_galley_id` (`galley_id`);

--
-- Indexes for table `issue_settings`
--
ALTER TABLE `issue_settings`
  ADD PRIMARY KEY (`issue_setting_id`),
  ADD UNIQUE KEY `issue_settings_unique` (`issue_id`,`locale`,`setting_name`),
  ADD KEY `issue_settings_issue_id` (`issue_id`),
  ADD KEY `issue_settings_name_value` (`setting_name`(50),`setting_value`(150));

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_reserved_at_index` (`queue`,`reserved_at`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `journals`
--
ALTER TABLE `journals`
  ADD PRIMARY KEY (`journal_id`),
  ADD UNIQUE KEY `journals_path` (`path`),
  ADD KEY `journals_issue_id` (`current_issue_id`);

--
-- Indexes for table `journal_settings`
--
ALTER TABLE `journal_settings`
  ADD PRIMARY KEY (`journal_setting_id`),
  ADD UNIQUE KEY `journal_settings_unique` (`journal_id`,`locale`,`setting_name`),
  ADD KEY `journal_settings_journal_id` (`journal_id`);

--
-- Indexes for table `library_files`
--
ALTER TABLE `library_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `library_files_context_id` (`context_id`),
  ADD KEY `library_files_submission_id` (`submission_id`);

--
-- Indexes for table `library_file_settings`
--
ALTER TABLE `library_file_settings`
  ADD PRIMARY KEY (`library_file_setting_id`),
  ADD UNIQUE KEY `library_file_settings_unique` (`file_id`,`locale`,`setting_name`),
  ADD KEY `library_file_settings_file_id` (`file_id`);

--
-- Indexes for table `metrics_context`
--
ALTER TABLE `metrics_context`
  ADD PRIMARY KEY (`metrics_context_id`),
  ADD KEY `metrics_context_load_id` (`load_id`),
  ADD KEY `metrics_context_context_id` (`context_id`);

--
-- Indexes for table `metrics_counter_submission_daily`
--
ALTER TABLE `metrics_counter_submission_daily`
  ADD PRIMARY KEY (`metrics_counter_submission_daily_id`),
  ADD UNIQUE KEY `msd_uc_load_id_context_id_submission_id_date` (`load_id`,`context_id`,`submission_id`,`date`),
  ADD KEY `msd_load_id` (`load_id`),
  ADD KEY `metrics_counter_submission_daily_context_id` (`context_id`),
  ADD KEY `metrics_counter_submission_daily_submission_id` (`submission_id`),
  ADD KEY `msd_context_id_submission_id` (`context_id`,`submission_id`);

--
-- Indexes for table `metrics_counter_submission_institution_daily`
--
ALTER TABLE `metrics_counter_submission_institution_daily`
  ADD PRIMARY KEY (`metrics_counter_submission_institution_daily_id`),
  ADD UNIQUE KEY `msid_uc_load_id_context_id_submission_id_institution_id_date` (`load_id`,`context_id`,`submission_id`,`institution_id`,`date`),
  ADD KEY `msid_load_id` (`load_id`),
  ADD KEY `metrics_counter_submission_institution_daily_context_id` (`context_id`),
  ADD KEY `metrics_counter_submission_institution_daily_submission_id` (`submission_id`),
  ADD KEY `metrics_counter_submission_institution_daily_institution_id` (`institution_id`),
  ADD KEY `msid_context_id_submission_id` (`context_id`,`submission_id`);

--
-- Indexes for table `metrics_counter_submission_institution_monthly`
--
ALTER TABLE `metrics_counter_submission_institution_monthly`
  ADD PRIMARY KEY (`metrics_counter_submission_institution_monthly_id`),
  ADD UNIQUE KEY `msim_uc_context_id_submission_id_institution_id_month` (`context_id`,`submission_id`,`institution_id`,`month`),
  ADD KEY `metrics_counter_submission_institution_monthly_context_id` (`context_id`),
  ADD KEY `metrics_counter_submission_institution_monthly_submission_id` (`submission_id`),
  ADD KEY `metrics_counter_submission_institution_monthly_institution_id` (`institution_id`),
  ADD KEY `msim_context_id_submission_id` (`context_id`,`submission_id`);

--
-- Indexes for table `metrics_counter_submission_monthly`
--
ALTER TABLE `metrics_counter_submission_monthly`
  ADD PRIMARY KEY (`metrics_counter_submission_monthly_id`),
  ADD UNIQUE KEY `msm_uc_context_id_submission_id_month` (`context_id`,`submission_id`,`month`),
  ADD KEY `metrics_counter_submission_monthly_context_id` (`context_id`),
  ADD KEY `metrics_counter_submission_monthly_submission_id` (`submission_id`),
  ADD KEY `msm_context_id_submission_id` (`context_id`,`submission_id`);

--
-- Indexes for table `metrics_issue`
--
ALTER TABLE `metrics_issue`
  ADD PRIMARY KEY (`metrics_issue_id`),
  ADD KEY `metrics_issue_load_id` (`load_id`),
  ADD KEY `metrics_issue_context_id` (`context_id`),
  ADD KEY `metrics_issue_issue_id` (`issue_id`),
  ADD KEY `metrics_issue_issue_galley_id` (`issue_galley_id`),
  ADD KEY `metrics_issue_context_id_issue_id` (`context_id`,`issue_id`);

--
-- Indexes for table `metrics_submission`
--
ALTER TABLE `metrics_submission`
  ADD PRIMARY KEY (`metrics_submission_id`),
  ADD KEY `ms_load_id` (`load_id`),
  ADD KEY `metrics_submission_context_id` (`context_id`),
  ADD KEY `metrics_submission_submission_id` (`submission_id`),
  ADD KEY `metrics_submission_representation_id` (`representation_id`),
  ADD KEY `metrics_submission_submission_file_id` (`submission_file_id`),
  ADD KEY `ms_context_id_submission_id_assoc_type_file_type` (`context_id`,`submission_id`,`assoc_type`,`file_type`);

--
-- Indexes for table `metrics_submission_geo_daily`
--
ALTER TABLE `metrics_submission_geo_daily`
  ADD PRIMARY KEY (`metrics_submission_geo_daily_id`),
  ADD UNIQUE KEY `msgd_uc_load_context_submission_c_r_c_date` (`load_id`,`context_id`,`submission_id`,`country`,`region`,`city`(80),`date`),
  ADD KEY `msgd_load_id` (`load_id`),
  ADD KEY `metrics_submission_geo_daily_context_id` (`context_id`),
  ADD KEY `metrics_submission_geo_daily_submission_id` (`submission_id`),
  ADD KEY `msgd_context_id_submission_id` (`context_id`,`submission_id`);

--
-- Indexes for table `metrics_submission_geo_monthly`
--
ALTER TABLE `metrics_submission_geo_monthly`
  ADD PRIMARY KEY (`metrics_submission_geo_monthly_id`),
  ADD UNIQUE KEY `msgm_uc_context_submission_c_r_c_month` (`context_id`,`submission_id`,`country`,`region`,`city`(80),`month`),
  ADD KEY `metrics_submission_geo_monthly_context_id` (`context_id`),
  ADD KEY `metrics_submission_geo_monthly_submission_id` (`submission_id`),
  ADD KEY `msgm_context_id_submission_id` (`context_id`,`submission_id`);

--
-- Indexes for table `navigation_menus`
--
ALTER TABLE `navigation_menus`
  ADD PRIMARY KEY (`navigation_menu_id`),
  ADD KEY `navigation_menus_context_id` (`context_id`);

--
-- Indexes for table `navigation_menu_items`
--
ALTER TABLE `navigation_menu_items`
  ADD PRIMARY KEY (`navigation_menu_item_id`),
  ADD KEY `navigation_menu_items_context_id` (`context_id`);

--
-- Indexes for table `navigation_menu_item_assignments`
--
ALTER TABLE `navigation_menu_item_assignments`
  ADD PRIMARY KEY (`navigation_menu_item_assignment_id`),
  ADD KEY `navigation_menu_item_assignments_navigation_menu_id` (`navigation_menu_id`),
  ADD KEY `navigation_menu_item_assignments_navigation_menu_item_id` (`navigation_menu_item_id`),
  ADD KEY `navigation_menu_item_assignments_parent_id` (`parent_id`);

--
-- Indexes for table `navigation_menu_item_assignment_settings`
--
ALTER TABLE `navigation_menu_item_assignment_settings`
  ADD PRIMARY KEY (`navigation_menu_item_assignment_setting_id`),
  ADD UNIQUE KEY `navigation_menu_item_assignment_settings_unique` (`navigation_menu_item_assignment_id`,`locale`,`setting_name`),
  ADD KEY `navigation_menu_item_assignment_settings_n_m_i_a_id` (`navigation_menu_item_assignment_id`);

--
-- Indexes for table `navigation_menu_item_settings`
--
ALTER TABLE `navigation_menu_item_settings`
  ADD PRIMARY KEY (`navigation_menu_item_setting_id`),
  ADD UNIQUE KEY `navigation_menu_item_settings_unique` (`navigation_menu_item_id`,`locale`,`setting_name`),
  ADD KEY `navigation_menu_item_settings_navigation_menu_item_id` (`navigation_menu_item_id`);

--
-- Indexes for table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`note_id`),
  ADD KEY `notes_user_id` (`user_id`),
  ADD KEY `notes_assoc` (`assoc_type`,`assoc_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `notifications_context_id` (`context_id`),
  ADD KEY `notifications_user_id` (`user_id`),
  ADD KEY `notifications_context_id_user_id` (`context_id`,`user_id`,`level`),
  ADD KEY `notifications_context_id_level` (`context_id`,`level`),
  ADD KEY `notifications_assoc` (`assoc_type`,`assoc_id`),
  ADD KEY `notifications_user_id_level` (`user_id`,`level`);

--
-- Indexes for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD PRIMARY KEY (`notification_setting_id`),
  ADD UNIQUE KEY `notification_settings_unique` (`notification_id`,`locale`,`setting_name`),
  ADD KEY `notification_settings_notification_id` (`notification_id`);

--
-- Indexes for table `notification_subscription_settings`
--
ALTER TABLE `notification_subscription_settings`
  ADD PRIMARY KEY (`setting_id`),
  ADD KEY `notification_subscription_settings_user_id` (`user_id`),
  ADD KEY `notification_subscription_settings_context` (`context_id`);

--
-- Indexes for table `oai_resumption_tokens`
--
ALTER TABLE `oai_resumption_tokens`
  ADD PRIMARY KEY (`oai_resumption_token_id`),
  ADD UNIQUE KEY `oai_resumption_tokens_unique` (`token`);

--
-- Indexes for table `plugin_settings`
--
ALTER TABLE `plugin_settings`
  ADD PRIMARY KEY (`plugin_setting_id`),
  ADD UNIQUE KEY `plugin_settings_unique` (`plugin_name`,`context_id`,`setting_name`),
  ADD KEY `plugin_settings_context_id` (`context_id`),
  ADD KEY `plugin_settings_plugin_name` (`plugin_name`);

--
-- Indexes for table `publications`
--
ALTER TABLE `publications`
  ADD PRIMARY KEY (`publication_id`),
  ADD KEY `publications_primary_contact_id` (`primary_contact_id`),
  ADD KEY `publications_section_id` (`section_id`),
  ADD KEY `publications_submission_id` (`submission_id`),
  ADD KEY `publications_doi_id` (`doi_id`),
  ADD KEY `publications_issue_id_index` (`issue_id`),
  ADD KEY `publications_url_path` (`url_path`);

--
-- Indexes for table `publication_categories`
--
ALTER TABLE `publication_categories`
  ADD PRIMARY KEY (`publication_category_id`),
  ADD UNIQUE KEY `publication_categories_id` (`publication_id`,`category_id`),
  ADD KEY `publication_categories_publication_id` (`publication_id`),
  ADD KEY `publication_categories_category_id` (`category_id`);

--
-- Indexes for table `publication_galleys`
--
ALTER TABLE `publication_galleys`
  ADD PRIMARY KEY (`galley_id`),
  ADD KEY `publication_galleys_publication_id` (`publication_id`),
  ADD KEY `publication_galleys_submission_file_id` (`submission_file_id`),
  ADD KEY `publication_galleys_doi_id` (`doi_id`),
  ADD KEY `publication_galleys_url_path` (`url_path`);

--
-- Indexes for table `publication_galley_settings`
--
ALTER TABLE `publication_galley_settings`
  ADD PRIMARY KEY (`publication_galley_setting_id`),
  ADD UNIQUE KEY `publication_galley_settings_unique` (`galley_id`,`locale`,`setting_name`),
  ADD KEY `publication_galley_settings_galley_id` (`galley_id`),
  ADD KEY `publication_galley_settings_name_value` (`setting_name`(50),`setting_value`(150));

--
-- Indexes for table `publication_settings`
--
ALTER TABLE `publication_settings`
  ADD PRIMARY KEY (`publication_setting_id`),
  ADD UNIQUE KEY `publication_settings_unique` (`publication_id`,`locale`,`setting_name`),
  ADD KEY `publication_settings_name_value` (`setting_name`(50),`setting_value`(150)),
  ADD KEY `publication_settings_publication_id` (`publication_id`);

--
-- Indexes for table `queries`
--
ALTER TABLE `queries`
  ADD PRIMARY KEY (`query_id`),
  ADD KEY `queries_assoc_id` (`assoc_type`,`assoc_id`);

--
-- Indexes for table `query_participants`
--
ALTER TABLE `query_participants`
  ADD PRIMARY KEY (`query_participant_id`),
  ADD UNIQUE KEY `query_participants_unique` (`query_id`,`user_id`),
  ADD KEY `query_participants_query_id` (`query_id`),
  ADD KEY `query_participants_user_id` (`user_id`);

--
-- Indexes for table `queued_payments`
--
ALTER TABLE `queued_payments`
  ADD PRIMARY KEY (`queued_payment_id`);

--
-- Indexes for table `reviewer_suggestions`
--
ALTER TABLE `reviewer_suggestions`
  ADD PRIMARY KEY (`reviewer_suggestion_id`),
  ADD KEY `reviewer_suggestions_suggesting_user_id` (`suggesting_user_id`),
  ADD KEY `reviewer_suggestions_submission_id` (`submission_id`),
  ADD KEY `reviewer_suggestions_approver_id_foreign` (`approver_id`),
  ADD KEY `reviewer_suggestions_reviewer_id_foreign` (`reviewer_id`);

--
-- Indexes for table `reviewer_suggestion_settings`
--
ALTER TABLE `reviewer_suggestion_settings`
  ADD UNIQUE KEY `reviewer_suggestion_settings_unique` (`reviewer_suggestion_id`,`locale`,`setting_name`),
  ADD KEY `reviewer_suggestion_settings_reviewer_suggestion_id` (`reviewer_suggestion_id`),
  ADD KEY `reviewer_suggestion_settings_locale_setting_name_index` (`setting_name`,`locale`);

--
-- Indexes for table `review_assignments`
--
ALTER TABLE `review_assignments`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `review_assignments_submission_id` (`submission_id`),
  ADD KEY `review_assignments_reviewer_id` (`reviewer_id`),
  ADD KEY `review_assignment_reviewer_round` (`review_round_id`,`reviewer_id`),
  ADD KEY `review_assignments_form_id` (`review_form_id`),
  ADD KEY `review_assignments_reviewer_review` (`reviewer_id`,`review_id`);

--
-- Indexes for table `review_assignment_settings`
--
ALTER TABLE `review_assignment_settings`
  ADD PRIMARY KEY (`review_assignment_settings_id`),
  ADD UNIQUE KEY `review_assignment_settings_unique` (`review_id`,`locale`,`setting_name`),
  ADD KEY `review_assignment_settings_review_id` (`review_id`);

--
-- Indexes for table `review_files`
--
ALTER TABLE `review_files`
  ADD PRIMARY KEY (`review_file_id`),
  ADD UNIQUE KEY `review_files_unique` (`review_id`,`submission_file_id`),
  ADD KEY `review_files_review_id` (`review_id`),
  ADD KEY `review_files_submission_file_id` (`submission_file_id`);

--
-- Indexes for table `review_forms`
--
ALTER TABLE `review_forms`
  ADD PRIMARY KEY (`review_form_id`);

--
-- Indexes for table `review_form_elements`
--
ALTER TABLE `review_form_elements`
  ADD PRIMARY KEY (`review_form_element_id`),
  ADD KEY `review_form_elements_review_form_id` (`review_form_id`);

--
-- Indexes for table `review_form_element_settings`
--
ALTER TABLE `review_form_element_settings`
  ADD PRIMARY KEY (`review_form_element_setting_id`),
  ADD UNIQUE KEY `review_form_element_settings_unique` (`review_form_element_id`,`locale`,`setting_name`),
  ADD KEY `review_form_element_settings_review_form_element_id` (`review_form_element_id`);

--
-- Indexes for table `review_form_responses`
--
ALTER TABLE `review_form_responses`
  ADD PRIMARY KEY (`review_form_response_id`),
  ADD KEY `review_form_responses_review_form_element_id` (`review_form_element_id`),
  ADD KEY `review_form_responses_review_id` (`review_id`),
  ADD KEY `review_form_responses_unique` (`review_form_element_id`,`review_id`);

--
-- Indexes for table `review_form_settings`
--
ALTER TABLE `review_form_settings`
  ADD PRIMARY KEY (`review_form_setting_id`),
  ADD UNIQUE KEY `review_form_settings_unique` (`review_form_id`,`locale`,`setting_name`),
  ADD KEY `review_form_settings_review_form_id` (`review_form_id`);

--
-- Indexes for table `review_rounds`
--
ALTER TABLE `review_rounds`
  ADD PRIMARY KEY (`review_round_id`),
  ADD UNIQUE KEY `review_rounds_submission_id_stage_id_round_pkey` (`submission_id`,`stage_id`,`round`),
  ADD KEY `review_rounds_submission_id` (`submission_id`);

--
-- Indexes for table `review_round_files`
--
ALTER TABLE `review_round_files`
  ADD PRIMARY KEY (`review_round_file_id`),
  ADD UNIQUE KEY `review_round_files_unique` (`submission_id`,`review_round_id`,`submission_file_id`),
  ADD KEY `review_round_files_submission_id` (`submission_id`),
  ADD KEY `review_round_files_review_round_id` (`review_round_id`),
  ADD KEY `review_round_files_submission_file_id` (`submission_file_id`);

--
-- Indexes for table `rors`
--
ALTER TABLE `rors`
  ADD PRIMARY KEY (`ror_id`),
  ADD UNIQUE KEY `rors_unique` (`ror`),
  ADD KEY `rors_display_locale` (`display_locale`),
  ADD KEY `rors_is_active` (`is_active`);

--
-- Indexes for table `ror_settings`
--
ALTER TABLE `ror_settings`
  ADD PRIMARY KEY (`ror_setting_id`),
  ADD UNIQUE KEY `ror_settings_unique` (`ror_id`,`locale`,`setting_name`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD KEY `sections_journal_id` (`journal_id`),
  ADD KEY `sections_review_form_id` (`review_form_id`);

--
-- Indexes for table `section_settings`
--
ALTER TABLE `section_settings`
  ADD PRIMARY KEY (`section_setting_id`),
  ADD UNIQUE KEY `section_settings_unique` (`section_id`,`locale`,`setting_name`),
  ADD KEY `section_settings_section_id` (`section_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `site`
--
ALTER TABLE `site`
  ADD PRIMARY KEY (`site_id`),
  ADD KEY `site_context_id` (`redirect_context_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`site_setting_id`),
  ADD UNIQUE KEY `site_settings_unique` (`setting_name`,`locale`);

--
-- Indexes for table `stage_assignments`
--
ALTER TABLE `stage_assignments`
  ADD PRIMARY KEY (`stage_assignment_id`),
  ADD UNIQUE KEY `stage_assignment` (`submission_id`,`user_group_id`,`user_id`),
  ADD KEY `stage_assignments_user_group_id` (`user_group_id`),
  ADD KEY `stage_assignments_user_id` (`user_id`),
  ADD KEY `stage_assignments_submission_id` (`submission_id`);

--
-- Indexes for table `static_pages`
--
ALTER TABLE `static_pages`
  ADD PRIMARY KEY (`static_page_id`),
  ADD KEY `static_pages_context_id` (`context_id`);

--
-- Indexes for table `static_page_settings`
--
ALTER TABLE `static_page_settings`
  ADD PRIMARY KEY (`static_page_setting_id`),
  ADD UNIQUE KEY `static_page_settings_unique` (`static_page_id`,`locale`,`setting_name`),
  ADD KEY `static_page_settings_static_page_id` (`static_page_id`);

--
-- Indexes for table `subeditor_submission_group`
--
ALTER TABLE `subeditor_submission_group`
  ADD PRIMARY KEY (`subeditor_submission_group_id`),
  ADD UNIQUE KEY `section_editors_unique` (`context_id`,`assoc_id`,`assoc_type`,`user_id`,`user_group_id`),
  ADD KEY `subeditor_submission_group_context_id` (`context_id`),
  ADD KEY `subeditor_submission_group_user_id` (`user_id`),
  ADD KEY `subeditor_submission_group_user_group_id` (`user_group_id`),
  ADD KEY `subeditor_submission_group_assoc_id` (`assoc_id`,`assoc_type`);

--
-- Indexes for table `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`submission_id`),
  ADD KEY `submissions_context_id` (`context_id`),
  ADD KEY `submissions_publication_id` (`current_publication_id`);

--
-- Indexes for table `submission_comments`
--
ALTER TABLE `submission_comments`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `submission_comments_submission_id` (`submission_id`),
  ADD KEY `submission_comments_author_id` (`author_id`);

--
-- Indexes for table `submission_files`
--
ALTER TABLE `submission_files`
  ADD PRIMARY KEY (`submission_file_id`),
  ADD KEY `submission_files_submission_id` (`submission_id`),
  ADD KEY `submission_files_file_id` (`file_id`),
  ADD KEY `submission_files_genre_id` (`genre_id`),
  ADD KEY `submission_files_uploader_user_id` (`uploader_user_id`),
  ADD KEY `submission_files_stage_assoc` (`file_stage`,`assoc_type`,`assoc_id`),
  ADD KEY `submission_files_source_submission_file_id` (`source_submission_file_id`);

--
-- Indexes for table `submission_file_revisions`
--
ALTER TABLE `submission_file_revisions`
  ADD PRIMARY KEY (`revision_id`),
  ADD KEY `submission_file_revisions_submission_file_id` (`submission_file_id`),
  ADD KEY `submission_file_revisions_file_id` (`file_id`);

--
-- Indexes for table `submission_file_settings`
--
ALTER TABLE `submission_file_settings`
  ADD PRIMARY KEY (`submission_file_setting_id`),
  ADD UNIQUE KEY `submission_file_settings_unique` (`submission_file_id`,`locale`,`setting_name`),
  ADD KEY `submission_file_settings_submission_file_id` (`submission_file_id`);

--
-- Indexes for table `submission_search_keyword_list`
--
ALTER TABLE `submission_search_keyword_list`
  ADD PRIMARY KEY (`keyword_id`),
  ADD UNIQUE KEY `submission_search_keyword_text` (`keyword_text`);

--
-- Indexes for table `submission_search_objects`
--
ALTER TABLE `submission_search_objects`
  ADD PRIMARY KEY (`object_id`),
  ADD KEY `submission_search_objects_submission_id` (`submission_id`);

--
-- Indexes for table `submission_search_object_keywords`
--
ALTER TABLE `submission_search_object_keywords`
  ADD PRIMARY KEY (`submission_search_object_keyword_id`),
  ADD UNIQUE KEY `submission_search_object_keywords_unique` (`object_id`,`pos`),
  ADD KEY `submission_search_object_keywords_object_id` (`object_id`),
  ADD KEY `submission_search_object_keywords_keyword_id` (`keyword_id`);

--
-- Indexes for table `submission_settings`
--
ALTER TABLE `submission_settings`
  ADD PRIMARY KEY (`submission_setting_id`),
  ADD UNIQUE KEY `submission_settings_unique` (`submission_id`,`locale`,`setting_name`),
  ADD KEY `submission_settings_submission_id` (`submission_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`subscription_id`),
  ADD KEY `subscriptions_journal_id` (`journal_id`),
  ADD KEY `subscriptions_user_id` (`user_id`),
  ADD KEY `subscriptions_type_id` (`type_id`);

--
-- Indexes for table `subscription_types`
--
ALTER TABLE `subscription_types`
  ADD PRIMARY KEY (`type_id`),
  ADD KEY `subscription_types_journal_id` (`journal_id`);

--
-- Indexes for table `subscription_type_settings`
--
ALTER TABLE `subscription_type_settings`
  ADD PRIMARY KEY (`subscription_type_setting_id`),
  ADD UNIQUE KEY `subscription_type_settings_unique` (`type_id`,`locale`,`setting_name`),
  ADD KEY `subscription_type_settings_type_id` (`type_id`);

--
-- Indexes for table `temporary_files`
--
ALTER TABLE `temporary_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `temporary_files_user_id` (`user_id`);

--
-- Indexes for table `usage_stats_institution_temporary_records`
--
ALTER TABLE `usage_stats_institution_temporary_records`
  ADD PRIMARY KEY (`usage_stats_temp_institution_id`),
  ADD UNIQUE KEY `usitr_load_id_line_number_institution_id` (`load_id`,`line_number`,`institution_id`),
  ADD KEY `usi_institution_id` (`institution_id`);

--
-- Indexes for table `usage_stats_total_temporary_records`
--
ALTER TABLE `usage_stats_total_temporary_records`
  ADD PRIMARY KEY (`usage_stats_temp_total_id`),
  ADD KEY `usage_stats_total_temporary_records_issue_id` (`issue_id`),
  ADD KEY `usage_stats_total_temporary_records_issue_galley_id` (`issue_galley_id`),
  ADD KEY `usage_stats_total_temporary_records_context_id` (`context_id`),
  ADD KEY `usage_stats_total_temporary_records_submission_id` (`submission_id`),
  ADD KEY `usage_stats_total_temporary_records_representation_id` (`representation_id`),
  ADD KEY `usage_stats_total_temporary_records_submission_file_id` (`submission_file_id`),
  ADD KEY `ust_load_id_context_id_ip_ua_url` (`load_id`,`context_id`,`ip`,`user_agent`,`canonical_url`);

--
-- Indexes for table `usage_stats_unique_item_investigations_temporary_records`
--
ALTER TABLE `usage_stats_unique_item_investigations_temporary_records`
  ADD PRIMARY KEY (`usage_stats_temp_unique_item_id`),
  ADD KEY `usii_context_id` (`context_id`),
  ADD KEY `usii_submission_id` (`submission_id`),
  ADD KEY `usii_representation_id` (`representation_id`),
  ADD KEY `usii_submission_file_id` (`submission_file_id`),
  ADD KEY `usii_load_id_context_id_ip_ua` (`load_id`,`context_id`,`ip`,`user_agent`);

--
-- Indexes for table `usage_stats_unique_item_requests_temporary_records`
--
ALTER TABLE `usage_stats_unique_item_requests_temporary_records`
  ADD PRIMARY KEY (`usage_stats_temp_item_id`),
  ADD KEY `usir_context_id` (`context_id`),
  ADD KEY `usir_submission_id` (`submission_id`),
  ADD KEY `usir_representation_id` (`representation_id`),
  ADD KEY `usir_submission_file_id` (`submission_file_id`),
  ADD KEY `usir_load_id_context_id_ip_ua` (`load_id`,`context_id`,`ip`,`user_agent`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `users_username` (`username`),
  ADD UNIQUE KEY `users_email` (`email`);

--
-- Indexes for table `user_groups`
--
ALTER TABLE `user_groups`
  ADD PRIMARY KEY (`user_group_id`),
  ADD KEY `user_groups_context_id` (`context_id`),
  ADD KEY `user_groups_user_group_id` (`user_group_id`),
  ADD KEY `user_groups_role_id` (`role_id`);

--
-- Indexes for table `user_group_settings`
--
ALTER TABLE `user_group_settings`
  ADD PRIMARY KEY (`user_group_setting_id`),
  ADD UNIQUE KEY `user_group_settings_unique` (`user_group_id`,`locale`,`setting_name`),
  ADD KEY `user_group_settings_user_group_id` (`user_group_id`);

--
-- Indexes for table `user_group_stage`
--
ALTER TABLE `user_group_stage`
  ADD PRIMARY KEY (`user_group_stage_id`),
  ADD UNIQUE KEY `user_group_stage_unique` (`context_id`,`user_group_id`,`stage_id`),
  ADD KEY `user_group_stage_context_id` (`context_id`),
  ADD KEY `user_group_stage_user_group_id` (`user_group_id`),
  ADD KEY `user_group_stage_stage_id` (`stage_id`);

--
-- Indexes for table `user_interests`
--
ALTER TABLE `user_interests`
  ADD PRIMARY KEY (`user_interest_id`),
  ADD UNIQUE KEY `u_e_pkey` (`user_id`,`controlled_vocab_entry_id`),
  ADD KEY `user_interests_user_id` (`user_id`),
  ADD KEY `user_interests_controlled_vocab_entry_id` (`controlled_vocab_entry_id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_setting_id`),
  ADD UNIQUE KEY `user_settings_unique` (`user_id`,`locale`,`setting_name`),
  ADD KEY `user_settings_user_id` (`user_id`),
  ADD KEY `user_settings_locale_setting_name_index` (`setting_name`,`locale`);

--
-- Indexes for table `user_user_groups`
--
ALTER TABLE `user_user_groups`
  ADD PRIMARY KEY (`user_user_group_id`),
  ADD KEY `user_user_groups_user_group_id` (`user_group_id`),
  ADD KEY `user_user_groups_user_id` (`user_id`);

--
-- Indexes for table `versions`
--
ALTER TABLE `versions`
  ADD PRIMARY KEY (`version_id`),
  ADD UNIQUE KEY `versions_unique` (`product_type`,`product`,`major`,`minor`,`revision`,`build`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcement_settings`
--
ALTER TABLE `announcement_settings`
  MODIFY `announcement_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcement_types`
--
ALTER TABLE `announcement_types`
  MODIFY `type_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcement_type_settings`
--
ALTER TABLE `announcement_type_settings`
  MODIFY `announcement_type_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `authors`
--
ALTER TABLE `authors`
  MODIFY `author_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `author_affiliations`
--
ALTER TABLE `author_affiliations`
  MODIFY `author_affiliation_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `author_affiliation_settings`
--
ALTER TABLE `author_affiliation_settings`
  MODIFY `author_affiliation_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `author_settings`
--
ALTER TABLE `author_settings`
  MODIFY `author_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=151;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_settings`
--
ALTER TABLE `category_settings`
  MODIFY `category_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `citations`
--
ALTER TABLE `citations`
  MODIFY `citation_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `citation_settings`
--
ALTER TABLE `citation_settings`
  MODIFY `citation_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `completed_payments`
--
ALTER TABLE `completed_payments`
  MODIFY `completed_payment_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `controlled_vocabs`
--
ALTER TABLE `controlled_vocabs`
  MODIFY `controlled_vocab_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `controlled_vocab_entries`
--
ALTER TABLE `controlled_vocab_entries`
  MODIFY `controlled_vocab_entry_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `controlled_vocab_entry_settings`
--
ALTER TABLE `controlled_vocab_entry_settings`
  MODIFY `controlled_vocab_entry_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_issue_orders`
--
ALTER TABLE `custom_issue_orders`
  MODIFY `custom_issue_order_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_section_orders`
--
ALTER TABLE `custom_section_orders`
  MODIFY `custom_section_order_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_object_tombstones`
--
ALTER TABLE `data_object_tombstones`
  MODIFY `tombstone_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_object_tombstone_oai_set_objects`
--
ALTER TABLE `data_object_tombstone_oai_set_objects`
  MODIFY `object_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_object_tombstone_settings`
--
ALTER TABLE `data_object_tombstone_settings`
  MODIFY `tombstone_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dois`
--
ALTER TABLE `dois`
  MODIFY `doi_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doi_settings`
--
ALTER TABLE `doi_settings`
  MODIFY `doi_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `edit_decisions`
--
ALTER TABLE `edit_decisions`
  MODIFY `edit_decision_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `email_log`
--
ALTER TABLE `email_log`
  MODIFY `log_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_log_users`
--
ALTER TABLE `email_log_users`
  MODIFY `email_log_user_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `email_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `email_templates_default_data`
--
ALTER TABLE `email_templates_default_data`
  MODIFY `email_templates_default_data_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=147;

--
-- AUTO_INCREMENT for table `email_templates_settings`
--
ALTER TABLE `email_templates_settings`
  MODIFY `email_template_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_log`
--
ALTER TABLE `event_log`
  MODIFY `log_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_log_settings`
--
ALTER TABLE `event_log_settings`
  MODIFY `event_log_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `file_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `filters`
--
ALTER TABLE `filters`
  MODIFY `filter_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `filter_groups`
--
ALTER TABLE `filter_groups`
  MODIFY `filter_group_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `filter_settings`
--
ALTER TABLE `filter_settings`
  MODIFY `filter_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `genres`
--
ALTER TABLE `genres`
  MODIFY `genre_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `genre_settings`
--
ALTER TABLE `genre_settings`
  MODIFY `genre_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `highlights`
--
ALTER TABLE `highlights`
  MODIFY `highlight_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `highlight_settings`
--
ALTER TABLE `highlight_settings`
  MODIFY `highlight_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `institutional_subscriptions`
--
ALTER TABLE `institutional_subscriptions`
  MODIFY `institutional_subscription_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `institutions`
--
ALTER TABLE `institutions`
  MODIFY `institution_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `institution_ip`
--
ALTER TABLE `institution_ip`
  MODIFY `institution_ip_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `institution_settings`
--
ALTER TABLE `institution_settings`
  MODIFY `institution_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invitations`
--
ALTER TABLE `invitations`
  MODIFY `invitation_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `issues`
--
ALTER TABLE `issues`
  MODIFY `issue_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `issue_files`
--
ALTER TABLE `issue_files`
  MODIFY `file_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `issue_galleys`
--
ALTER TABLE `issue_galleys`
  MODIFY `galley_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `issue_galley_settings`
--
ALTER TABLE `issue_galley_settings`
  MODIFY `issue_galley_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `issue_settings`
--
ALTER TABLE `issue_settings`
  MODIFY `issue_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `journals`
--
ALTER TABLE `journals`
  MODIFY `journal_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `journal_settings`
--
ALTER TABLE `journal_settings`
  MODIFY `journal_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `library_files`
--
ALTER TABLE `library_files`
  MODIFY `file_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `library_file_settings`
--
ALTER TABLE `library_file_settings`
  MODIFY `library_file_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metrics_context`
--
ALTER TABLE `metrics_context`
  MODIFY `metrics_context_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metrics_counter_submission_daily`
--
ALTER TABLE `metrics_counter_submission_daily`
  MODIFY `metrics_counter_submission_daily_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metrics_counter_submission_institution_daily`
--
ALTER TABLE `metrics_counter_submission_institution_daily`
  MODIFY `metrics_counter_submission_institution_daily_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metrics_counter_submission_institution_monthly`
--
ALTER TABLE `metrics_counter_submission_institution_monthly`
  MODIFY `metrics_counter_submission_institution_monthly_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metrics_counter_submission_monthly`
--
ALTER TABLE `metrics_counter_submission_monthly`
  MODIFY `metrics_counter_submission_monthly_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metrics_issue`
--
ALTER TABLE `metrics_issue`
  MODIFY `metrics_issue_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metrics_submission`
--
ALTER TABLE `metrics_submission`
  MODIFY `metrics_submission_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metrics_submission_geo_daily`
--
ALTER TABLE `metrics_submission_geo_daily`
  MODIFY `metrics_submission_geo_daily_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `metrics_submission_geo_monthly`
--
ALTER TABLE `metrics_submission_geo_monthly`
  MODIFY `metrics_submission_geo_monthly_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `navigation_menus`
--
ALTER TABLE `navigation_menus`
  MODIFY `navigation_menu_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `navigation_menu_items`
--
ALTER TABLE `navigation_menu_items`
  MODIFY `navigation_menu_item_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `navigation_menu_item_assignments`
--
ALTER TABLE `navigation_menu_item_assignments`
  MODIFY `navigation_menu_item_assignment_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `navigation_menu_item_assignment_settings`
--
ALTER TABLE `navigation_menu_item_assignment_settings`
  MODIFY `navigation_menu_item_assignment_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `navigation_menu_item_settings`
--
ALTER TABLE `navigation_menu_item_settings`
  MODIFY `navigation_menu_item_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `notes`
--
ALTER TABLE `notes`
  MODIFY `note_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `notification_settings`
--
ALTER TABLE `notification_settings`
  MODIFY `notification_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notification_subscription_settings`
--
ALTER TABLE `notification_subscription_settings`
  MODIFY `setting_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `oai_resumption_tokens`
--
ALTER TABLE `oai_resumption_tokens`
  MODIFY `oai_resumption_token_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plugin_settings`
--
ALTER TABLE `plugin_settings`
  MODIFY `plugin_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `publications`
--
ALTER TABLE `publications`
  MODIFY `publication_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `publication_categories`
--
ALTER TABLE `publication_categories`
  MODIFY `publication_category_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `publication_galleys`
--
ALTER TABLE `publication_galleys`
  MODIFY `galley_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `publication_galley_settings`
--
ALTER TABLE `publication_galley_settings`
  MODIFY `publication_galley_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `publication_settings`
--
ALTER TABLE `publication_settings`
  MODIFY `publication_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `queries`
--
ALTER TABLE `queries`
  MODIFY `query_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `query_participants`
--
ALTER TABLE `query_participants`
  MODIFY `query_participant_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queued_payments`
--
ALTER TABLE `queued_payments`
  MODIFY `queued_payment_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviewer_suggestions`
--
ALTER TABLE `reviewer_suggestions`
  MODIFY `reviewer_suggestion_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_assignments`
--
ALTER TABLE `review_assignments`
  MODIFY `review_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_assignment_settings`
--
ALTER TABLE `review_assignment_settings`
  MODIFY `review_assignment_settings_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key.';

--
-- AUTO_INCREMENT for table `review_files`
--
ALTER TABLE `review_files`
  MODIFY `review_file_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_forms`
--
ALTER TABLE `review_forms`
  MODIFY `review_form_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_form_elements`
--
ALTER TABLE `review_form_elements`
  MODIFY `review_form_element_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_form_element_settings`
--
ALTER TABLE `review_form_element_settings`
  MODIFY `review_form_element_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_form_responses`
--
ALTER TABLE `review_form_responses`
  MODIFY `review_form_response_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_form_settings`
--
ALTER TABLE `review_form_settings`
  MODIFY `review_form_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `review_rounds`
--
ALTER TABLE `review_rounds`
  MODIFY `review_round_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `review_round_files`
--
ALTER TABLE `review_round_files`
  MODIFY `review_round_file_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rors`
--
ALTER TABLE `rors`
  MODIFY `ror_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ror_settings`
--
ALTER TABLE `ror_settings`
  MODIFY `ror_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `section_settings`
--
ALTER TABLE `section_settings`
  MODIFY `section_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `site`
--
ALTER TABLE `site`
  MODIFY `site_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `site_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `stage_assignments`
--
ALTER TABLE `stage_assignments`
  MODIFY `stage_assignment_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `static_pages`
--
ALTER TABLE `static_pages`
  MODIFY `static_page_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `static_page_settings`
--
ALTER TABLE `static_page_settings`
  MODIFY `static_page_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subeditor_submission_group`
--
ALTER TABLE `subeditor_submission_group`
  MODIFY `subeditor_submission_group_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submissions`
--
ALTER TABLE `submissions`
  MODIFY `submission_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `submission_comments`
--
ALTER TABLE `submission_comments`
  MODIFY `comment_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `submission_files`
--
ALTER TABLE `submission_files`
  MODIFY `submission_file_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `submission_file_revisions`
--
ALTER TABLE `submission_file_revisions`
  MODIFY `revision_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `submission_file_settings`
--
ALTER TABLE `submission_file_settings`
  MODIFY `submission_file_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `submission_search_keyword_list`
--
ALTER TABLE `submission_search_keyword_list`
  MODIFY `keyword_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=678;

--
-- AUTO_INCREMENT for table `submission_search_objects`
--
ALTER TABLE `submission_search_objects`
  MODIFY `object_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT for table `submission_search_object_keywords`
--
ALTER TABLE `submission_search_object_keywords`
  MODIFY `submission_search_object_keyword_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9697;

--
-- AUTO_INCREMENT for table `submission_settings`
--
ALTER TABLE `submission_settings`
  MODIFY `submission_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `subscription_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_types`
--
ALTER TABLE `subscription_types`
  MODIFY `type_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_type_settings`
--
ALTER TABLE `subscription_type_settings`
  MODIFY `subscription_type_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `temporary_files`
--
ALTER TABLE `temporary_files`
  MODIFY `file_id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usage_stats_institution_temporary_records`
--
ALTER TABLE `usage_stats_institution_temporary_records`
  MODIFY `usage_stats_temp_institution_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usage_stats_total_temporary_records`
--
ALTER TABLE `usage_stats_total_temporary_records`
  MODIFY `usage_stats_temp_total_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usage_stats_unique_item_investigations_temporary_records`
--
ALTER TABLE `usage_stats_unique_item_investigations_temporary_records`
  MODIFY `usage_stats_temp_unique_item_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usage_stats_unique_item_requests_temporary_records`
--
ALTER TABLE `usage_stats_unique_item_requests_temporary_records`
  MODIFY `usage_stats_temp_item_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_groups`
--
ALTER TABLE `user_groups`
  MODIFY `user_group_id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_group_settings`
--
ALTER TABLE `user_group_settings`
  MODIFY `user_group_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=109;

--
-- AUTO_INCREMENT for table `user_group_stage`
--
ALTER TABLE `user_group_stage`
  MODIFY `user_group_stage_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `user_interests`
--
ALTER TABLE `user_interests`
  MODIFY `user_interest_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_settings`
--
ALTER TABLE `user_settings`
  MODIFY `user_setting_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `user_user_groups`
--
ALTER TABLE `user_user_groups`
  MODIFY `user_user_group_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `versions`
--
ALTER TABLE `versions`
  MODIFY `version_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `announcement_types` (`type_id`) ON DELETE SET NULL;

--
-- Constraints for table `announcement_settings`
--
ALTER TABLE `announcement_settings`
  ADD CONSTRAINT `announcement_settings_announcement_id_foreign` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`announcement_id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_types`
--
ALTER TABLE `announcement_types`
  ADD CONSTRAINT `announcement_types_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `announcement_type_settings`
--
ALTER TABLE `announcement_type_settings`
  ADD CONSTRAINT `announcement_type_settings_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `announcement_types` (`type_id`) ON DELETE CASCADE;

--
-- Constraints for table `authors`
--
ALTER TABLE `authors`
  ADD CONSTRAINT `authors_publication_id_foreign` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`publication_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `authors_user_group_id_foreign` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`user_group_id`) ON DELETE CASCADE;

--
-- Constraints for table `author_affiliations`
--
ALTER TABLE `author_affiliations`
  ADD CONSTRAINT `author_affiliations_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `authors` (`author_id`) ON DELETE CASCADE;

--
-- Constraints for table `author_affiliation_settings`
--
ALTER TABLE `author_affiliation_settings`
  ADD CONSTRAINT `author_affiliation_settings_author_affiliation_id_foreign` FOREIGN KEY (`author_affiliation_id`) REFERENCES `author_affiliations` (`author_affiliation_id`) ON DELETE CASCADE;

--
-- Constraints for table `author_settings`
--
ALTER TABLE `author_settings`
  ADD CONSTRAINT `author_settings_author_id` FOREIGN KEY (`author_id`) REFERENCES `authors` (`author_id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL;

--
-- Constraints for table `category_settings`
--
ALTER TABLE `category_settings`
  ADD CONSTRAINT `category_settings_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE;

--
-- Constraints for table `citations`
--
ALTER TABLE `citations`
  ADD CONSTRAINT `citations_publication` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`publication_id`) ON DELETE CASCADE;

--
-- Constraints for table `citation_settings`
--
ALTER TABLE `citation_settings`
  ADD CONSTRAINT `citation_settings_citation_id` FOREIGN KEY (`citation_id`) REFERENCES `citations` (`citation_id`) ON DELETE CASCADE;

--
-- Constraints for table `completed_payments`
--
ALTER TABLE `completed_payments`
  ADD CONSTRAINT `completed_payments_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `completed_payments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `controlled_vocab_entries`
--
ALTER TABLE `controlled_vocab_entries`
  ADD CONSTRAINT `controlled_vocab_entries_controlled_vocab_id_foreign` FOREIGN KEY (`controlled_vocab_id`) REFERENCES `controlled_vocabs` (`controlled_vocab_id`) ON DELETE CASCADE;

--
-- Constraints for table `controlled_vocab_entry_settings`
--
ALTER TABLE `controlled_vocab_entry_settings`
  ADD CONSTRAINT `c_v_e_s_entry_id` FOREIGN KEY (`controlled_vocab_entry_id`) REFERENCES `controlled_vocab_entries` (`controlled_vocab_entry_id`) ON DELETE CASCADE;

--
-- Constraints for table `custom_issue_orders`
--
ALTER TABLE `custom_issue_orders`
  ADD CONSTRAINT `custom_issue_orders_issue_id` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`issue_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `custom_issue_orders_journal_id` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `custom_section_orders`
--
ALTER TABLE `custom_section_orders`
  ADD CONSTRAINT `custom_section_orders_issue_id` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`issue_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `custom_section_orders_section_id` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE;

--
-- Constraints for table `data_object_tombstone_oai_set_objects`
--
ALTER TABLE `data_object_tombstone_oai_set_objects`
  ADD CONSTRAINT `data_object_tombstone_oai_set_objects_tombstone_id` FOREIGN KEY (`tombstone_id`) REFERENCES `data_object_tombstones` (`tombstone_id`) ON DELETE CASCADE;

--
-- Constraints for table `data_object_tombstone_settings`
--
ALTER TABLE `data_object_tombstone_settings`
  ADD CONSTRAINT `data_object_tombstone_settings_tombstone_id` FOREIGN KEY (`tombstone_id`) REFERENCES `data_object_tombstones` (`tombstone_id`) ON DELETE CASCADE;

--
-- Constraints for table `dois`
--
ALTER TABLE `dois`
  ADD CONSTRAINT `dois_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `doi_settings`
--
ALTER TABLE `doi_settings`
  ADD CONSTRAINT `doi_settings_doi_id_foreign` FOREIGN KEY (`doi_id`) REFERENCES `dois` (`doi_id`) ON DELETE CASCADE;

--
-- Constraints for table `edit_decisions`
--
ALTER TABLE `edit_decisions`
  ADD CONSTRAINT `edit_decisions_editor_id` FOREIGN KEY (`editor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `edit_decisions_review_round_id_foreign` FOREIGN KEY (`review_round_id`) REFERENCES `review_rounds` (`review_round_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `edit_decisions_submission_id` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `email_log`
--
ALTER TABLE `email_log`
  ADD CONSTRAINT `email_log_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `email_log_users`
--
ALTER TABLE `email_log_users`
  ADD CONSTRAINT `email_log_users_email_log_id_foreign` FOREIGN KEY (`email_log_id`) REFERENCES `email_log` (`log_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `email_log_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD CONSTRAINT `email_templates_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `email_templates_settings`
--
ALTER TABLE `email_templates_settings`
  ADD CONSTRAINT `email_templates_settings_email_id` FOREIGN KEY (`email_id`) REFERENCES `email_templates` (`email_id`) ON DELETE CASCADE;

--
-- Constraints for table `event_log`
--
ALTER TABLE `event_log`
  ADD CONSTRAINT `event_log_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `event_log_settings`
--
ALTER TABLE `event_log_settings`
  ADD CONSTRAINT `event_log_settings_log_id` FOREIGN KEY (`log_id`) REFERENCES `event_log` (`log_id`) ON DELETE CASCADE;

--
-- Constraints for table `filters`
--
ALTER TABLE `filters`
  ADD CONSTRAINT `filters_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `filters_filter_group_id_foreign` FOREIGN KEY (`filter_group_id`) REFERENCES `filter_groups` (`filter_group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `filters_parent_filter_id_foreign` FOREIGN KEY (`parent_filter_id`) REFERENCES `filters` (`filter_id`) ON DELETE CASCADE;

--
-- Constraints for table `filter_settings`
--
ALTER TABLE `filter_settings`
  ADD CONSTRAINT `filter_settings_filter_id_foreign` FOREIGN KEY (`filter_id`) REFERENCES `filters` (`filter_id`) ON DELETE CASCADE;

--
-- Constraints for table `genres`
--
ALTER TABLE `genres`
  ADD CONSTRAINT `genres_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `genre_settings`
--
ALTER TABLE `genre_settings`
  ADD CONSTRAINT `genre_settings_genre_id_foreign` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`genre_id`) ON DELETE CASCADE;

--
-- Constraints for table `highlights`
--
ALTER TABLE `highlights`
  ADD CONSTRAINT `highlights_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `highlight_settings`
--
ALTER TABLE `highlight_settings`
  ADD CONSTRAINT `highlight_settings_highlight_id_foreign` FOREIGN KEY (`highlight_id`) REFERENCES `highlights` (`highlight_id`) ON DELETE CASCADE;

--
-- Constraints for table `institutional_subscriptions`
--
ALTER TABLE `institutional_subscriptions`
  ADD CONSTRAINT `institutional_subscriptions_institution_id_foreign` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `institutional_subscriptions_subscription_id` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`subscription_id`) ON DELETE CASCADE;

--
-- Constraints for table `institutions`
--
ALTER TABLE `institutions`
  ADD CONSTRAINT `institutions_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `institution_ip`
--
ALTER TABLE `institution_ip`
  ADD CONSTRAINT `institution_ip_institution_id_foreign` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE;

--
-- Constraints for table `institution_settings`
--
ALTER TABLE `institution_settings`
  ADD CONSTRAINT `institution_settings_institution_id_foreign` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE;

--
-- Constraints for table `invitations`
--
ALTER TABLE `invitations`
  ADD CONSTRAINT `invitations_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invitations_inviter_id_foreign` FOREIGN KEY (`inviter_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `issues`
--
ALTER TABLE `issues`
  ADD CONSTRAINT `issues_doi_id_foreign` FOREIGN KEY (`doi_id`) REFERENCES `dois` (`doi_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `issues_journal_id` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `issue_files`
--
ALTER TABLE `issue_files`
  ADD CONSTRAINT `issue_files_issue_id` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`issue_id`) ON DELETE CASCADE;

--
-- Constraints for table `issue_galleys`
--
ALTER TABLE `issue_galleys`
  ADD CONSTRAINT `issue_galleys_file_id` FOREIGN KEY (`file_id`) REFERENCES `issue_files` (`file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `issue_galleys_issue_id` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`issue_id`) ON DELETE CASCADE;

--
-- Constraints for table `issue_galley_settings`
--
ALTER TABLE `issue_galley_settings`
  ADD CONSTRAINT `issue_galleys_settings_galley_id` FOREIGN KEY (`galley_id`) REFERENCES `issue_galleys` (`galley_id`) ON DELETE CASCADE;

--
-- Constraints for table `issue_settings`
--
ALTER TABLE `issue_settings`
  ADD CONSTRAINT `issue_settings_issue_id` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`issue_id`) ON DELETE CASCADE;

--
-- Constraints for table `journals`
--
ALTER TABLE `journals`
  ADD CONSTRAINT `journals_current_issue_id_foreign` FOREIGN KEY (`current_issue_id`) REFERENCES `issues` (`issue_id`) ON DELETE SET NULL;

--
-- Constraints for table `journal_settings`
--
ALTER TABLE `journal_settings`
  ADD CONSTRAINT `journal_settings_journal_id` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `library_files`
--
ALTER TABLE `library_files`
  ADD CONSTRAINT `library_files_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `library_files_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `library_file_settings`
--
ALTER TABLE `library_file_settings`
  ADD CONSTRAINT `library_file_settings_file_id_foreign` FOREIGN KEY (`file_id`) REFERENCES `library_files` (`file_id`) ON DELETE CASCADE;

--
-- Constraints for table `metrics_context`
--
ALTER TABLE `metrics_context`
  ADD CONSTRAINT `metrics_context_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `metrics_counter_submission_daily`
--
ALTER TABLE `metrics_counter_submission_daily`
  ADD CONSTRAINT `msd_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `msd_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `metrics_counter_submission_institution_daily`
--
ALTER TABLE `metrics_counter_submission_institution_daily`
  ADD CONSTRAINT `msid_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `msid_institution_id_foreign` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `msid_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `metrics_counter_submission_institution_monthly`
--
ALTER TABLE `metrics_counter_submission_institution_monthly`
  ADD CONSTRAINT `msim_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `msim_institution_id_foreign` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `msim_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `metrics_counter_submission_monthly`
--
ALTER TABLE `metrics_counter_submission_monthly`
  ADD CONSTRAINT `msm_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `msm_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `metrics_issue`
--
ALTER TABLE `metrics_issue`
  ADD CONSTRAINT `metrics_issue_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `metrics_issue_issue_galley_id_foreign` FOREIGN KEY (`issue_galley_id`) REFERENCES `issue_galleys` (`galley_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `metrics_issue_issue_id_foreign` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`issue_id`) ON DELETE CASCADE;

--
-- Constraints for table `metrics_submission`
--
ALTER TABLE `metrics_submission`
  ADD CONSTRAINT `metrics_submission_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `metrics_submission_representation_id_foreign` FOREIGN KEY (`representation_id`) REFERENCES `publication_galleys` (`galley_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `metrics_submission_submission_file_id_foreign` FOREIGN KEY (`submission_file_id`) REFERENCES `submission_files` (`submission_file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `metrics_submission_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `metrics_submission_geo_daily`
--
ALTER TABLE `metrics_submission_geo_daily`
  ADD CONSTRAINT `msgd_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `msgd_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `metrics_submission_geo_monthly`
--
ALTER TABLE `metrics_submission_geo_monthly`
  ADD CONSTRAINT `msgm_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `msgm_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `navigation_menus`
--
ALTER TABLE `navigation_menus`
  ADD CONSTRAINT `navigation_menus_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `navigation_menu_items`
--
ALTER TABLE `navigation_menu_items`
  ADD CONSTRAINT `navigation_menu_items_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `navigation_menu_item_assignments`
--
ALTER TABLE `navigation_menu_item_assignments`
  ADD CONSTRAINT `navigation_menu_item_assignments_navigation_menu_id_foreign` FOREIGN KEY (`navigation_menu_id`) REFERENCES `navigation_menus` (`navigation_menu_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `navigation_menu_item_assignments_navigation_menu_item_id_foreign` FOREIGN KEY (`navigation_menu_item_id`) REFERENCES `navigation_menu_items` (`navigation_menu_item_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `navigation_menu_item_assignments_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `navigation_menu_items` (`navigation_menu_item_id`) ON DELETE CASCADE;

--
-- Constraints for table `navigation_menu_item_assignment_settings`
--
ALTER TABLE `navigation_menu_item_assignment_settings`
  ADD CONSTRAINT `assignment_settings_navigation_menu_item_assignment_id` FOREIGN KEY (`navigation_menu_item_assignment_id`) REFERENCES `navigation_menu_item_assignments` (`navigation_menu_item_assignment_id`) ON DELETE CASCADE;

--
-- Constraints for table `navigation_menu_item_settings`
--
ALTER TABLE `navigation_menu_item_settings`
  ADD CONSTRAINT `navigation_menu_item_settings_navigation_menu_id` FOREIGN KEY (`navigation_menu_item_id`) REFERENCES `navigation_menu_items` (`navigation_menu_item_id`) ON DELETE CASCADE;

--
-- Constraints for table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_settings`
--
ALTER TABLE `notification_settings`
  ADD CONSTRAINT `notification_settings_notification_id_foreign` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`notification_id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_subscription_settings`
--
ALTER TABLE `notification_subscription_settings`
  ADD CONSTRAINT `notification_subscription_settings_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notification_subscription_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `plugin_settings`
--
ALTER TABLE `plugin_settings`
  ADD CONSTRAINT `plugin_settings_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `publications`
--
ALTER TABLE `publications`
  ADD CONSTRAINT `publications_doi_id_foreign` FOREIGN KEY (`doi_id`) REFERENCES `dois` (`doi_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `publications_issue_id_foreign` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`issue_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `publications_primary_contact_id` FOREIGN KEY (`primary_contact_id`) REFERENCES `authors` (`author_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `publications_section_id` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `publications_submission_id` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `publication_categories`
--
ALTER TABLE `publication_categories`
  ADD CONSTRAINT `publication_categories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `publication_categories_publication_id_foreign` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`publication_id`) ON DELETE CASCADE;

--
-- Constraints for table `publication_galleys`
--
ALTER TABLE `publication_galleys`
  ADD CONSTRAINT `publication_galleys_doi_id_foreign` FOREIGN KEY (`doi_id`) REFERENCES `dois` (`doi_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `publication_galleys_publication_id` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`publication_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `publication_galleys_submission_file_id_foreign` FOREIGN KEY (`submission_file_id`) REFERENCES `submission_files` (`submission_file_id`);

--
-- Constraints for table `publication_galley_settings`
--
ALTER TABLE `publication_galley_settings`
  ADD CONSTRAINT `publication_galley_settings_galley_id` FOREIGN KEY (`galley_id`) REFERENCES `publication_galleys` (`galley_id`) ON DELETE CASCADE;

--
-- Constraints for table `publication_settings`
--
ALTER TABLE `publication_settings`
  ADD CONSTRAINT `publication_settings_publication_id` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`publication_id`) ON DELETE CASCADE;

--
-- Constraints for table `query_participants`
--
ALTER TABLE `query_participants`
  ADD CONSTRAINT `query_participants_query_id_foreign` FOREIGN KEY (`query_id`) REFERENCES `queries` (`query_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `query_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `reviewer_suggestions`
--
ALTER TABLE `reviewer_suggestions`
  ADD CONSTRAINT `reviewer_suggestions_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviewer_suggestions_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviewer_suggestions_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviewer_suggestions_suggesting_user_id_foreign` FOREIGN KEY (`suggesting_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `reviewer_suggestion_settings`
--
ALTER TABLE `reviewer_suggestion_settings`
  ADD CONSTRAINT `reviewer_suggestion_settings_reviewer_suggestion_id_foreign` FOREIGN KEY (`reviewer_suggestion_id`) REFERENCES `reviewer_suggestions` (`reviewer_suggestion_id`) ON DELETE CASCADE;

--
-- Constraints for table `review_assignments`
--
ALTER TABLE `review_assignments`
  ADD CONSTRAINT `review_assignments_review_form_id_foreign` FOREIGN KEY (`review_form_id`) REFERENCES `review_forms` (`review_form_id`),
  ADD CONSTRAINT `review_assignments_review_round_id_foreign` FOREIGN KEY (`review_round_id`) REFERENCES `review_rounds` (`review_round_id`),
  ADD CONSTRAINT `review_assignments_reviewer_id_foreign` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `review_assignments_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`);

--
-- Constraints for table `review_assignment_settings`
--
ALTER TABLE `review_assignment_settings`
  ADD CONSTRAINT `review_assignment_settings_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `review_assignments` (`review_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `review_files`
--
ALTER TABLE `review_files`
  ADD CONSTRAINT `review_files_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `review_assignments` (`review_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_files_submission_file_id_foreign` FOREIGN KEY (`submission_file_id`) REFERENCES `submission_files` (`submission_file_id`) ON DELETE CASCADE;

--
-- Constraints for table `review_form_elements`
--
ALTER TABLE `review_form_elements`
  ADD CONSTRAINT `review_form_elements_review_form_id` FOREIGN KEY (`review_form_id`) REFERENCES `review_forms` (`review_form_id`) ON DELETE CASCADE;

--
-- Constraints for table `review_form_element_settings`
--
ALTER TABLE `review_form_element_settings`
  ADD CONSTRAINT `review_form_element_settings_review_form_element_id` FOREIGN KEY (`review_form_element_id`) REFERENCES `review_form_elements` (`review_form_element_id`) ON DELETE CASCADE;

--
-- Constraints for table `review_form_responses`
--
ALTER TABLE `review_form_responses`
  ADD CONSTRAINT `review_form_responses_review_form_element_id_foreign` FOREIGN KEY (`review_form_element_id`) REFERENCES `review_form_elements` (`review_form_element_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_form_responses_review_id_foreign` FOREIGN KEY (`review_id`) REFERENCES `review_assignments` (`review_id`) ON DELETE CASCADE;

--
-- Constraints for table `review_form_settings`
--
ALTER TABLE `review_form_settings`
  ADD CONSTRAINT `review_form_settings_review_form_id` FOREIGN KEY (`review_form_id`) REFERENCES `review_forms` (`review_form_id`) ON DELETE CASCADE;

--
-- Constraints for table `review_rounds`
--
ALTER TABLE `review_rounds`
  ADD CONSTRAINT `review_rounds_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`);

--
-- Constraints for table `review_round_files`
--
ALTER TABLE `review_round_files`
  ADD CONSTRAINT `review_round_files_review_round_id_foreign` FOREIGN KEY (`review_round_id`) REFERENCES `review_rounds` (`review_round_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_round_files_submission_file_id_foreign` FOREIGN KEY (`submission_file_id`) REFERENCES `submission_files` (`submission_file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `review_round_files_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `ror_settings`
--
ALTER TABLE `ror_settings`
  ADD CONSTRAINT `ror_settings_ror_id_foreign` FOREIGN KEY (`ror_id`) REFERENCES `rors` (`ror_id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_journal_id` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sections_review_form_id` FOREIGN KEY (`review_form_id`) REFERENCES `review_forms` (`review_form_id`) ON DELETE SET NULL;

--
-- Constraints for table `section_settings`
--
ALTER TABLE `section_settings`
  ADD CONSTRAINT `section_settings_section_id` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE;

--
-- Constraints for table `sessions`
--
ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `site`
--
ALTER TABLE `site`
  ADD CONSTRAINT `site_redirect_context_id_foreign` FOREIGN KEY (`redirect_context_id`) REFERENCES `journals` (`journal_id`) ON DELETE SET NULL;

--
-- Constraints for table `stage_assignments`
--
ALTER TABLE `stage_assignments`
  ADD CONSTRAINT `stage_assignments_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_assignments_user_group_id` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`user_group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_assignments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `static_pages`
--
ALTER TABLE `static_pages`
  ADD CONSTRAINT `static_pages_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `static_page_settings`
--
ALTER TABLE `static_page_settings`
  ADD CONSTRAINT `static_page_settings_static_page_id` FOREIGN KEY (`static_page_id`) REFERENCES `static_pages` (`static_page_id`) ON DELETE CASCADE;

--
-- Constraints for table `subeditor_submission_group`
--
ALTER TABLE `subeditor_submission_group`
  ADD CONSTRAINT `section_editors_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subeditor_submission_group_user_group_id_foreign` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`user_group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subeditor_submission_group_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `submissions_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submissions_publication_id` FOREIGN KEY (`current_publication_id`) REFERENCES `publications` (`publication_id`) ON DELETE SET NULL;

--
-- Constraints for table `submission_comments`
--
ALTER TABLE `submission_comments`
  ADD CONSTRAINT `submission_comments_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_comments_submission_id` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_files`
--
ALTER TABLE `submission_files`
  ADD CONSTRAINT `submission_files_file_id_foreign` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_files_genre_id_foreign` FOREIGN KEY (`genre_id`) REFERENCES `genres` (`genre_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `submission_files_source_submission_file_id_foreign` FOREIGN KEY (`source_submission_file_id`) REFERENCES `submission_files` (`submission_file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_files_submission_id` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_files_uploader_user_id_foreign` FOREIGN KEY (`uploader_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `submission_file_revisions`
--
ALTER TABLE `submission_file_revisions`
  ADD CONSTRAINT `submission_file_revisions_file_id_foreign` FOREIGN KEY (`file_id`) REFERENCES `files` (`file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_file_revisions_submission_file_id_foreign` FOREIGN KEY (`submission_file_id`) REFERENCES `submission_files` (`submission_file_id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_file_settings`
--
ALTER TABLE `submission_file_settings`
  ADD CONSTRAINT `submission_file_settings_submission_file_id_foreign` FOREIGN KEY (`submission_file_id`) REFERENCES `submission_files` (`submission_file_id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_search_objects`
--
ALTER TABLE `submission_search_objects`
  ADD CONSTRAINT `submission_search_object_submission` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_search_object_keywords`
--
ALTER TABLE `submission_search_object_keywords`
  ADD CONSTRAINT `submission_search_object_keywords_keyword_id` FOREIGN KEY (`keyword_id`) REFERENCES `submission_search_keyword_list` (`keyword_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `submission_search_object_keywords_object_id_foreign` FOREIGN KEY (`object_id`) REFERENCES `submission_search_objects` (`object_id`) ON DELETE CASCADE;

--
-- Constraints for table `submission_settings`
--
ALTER TABLE `submission_settings`
  ADD CONSTRAINT `submission_settings_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_journal_id` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_type_id` FOREIGN KEY (`type_id`) REFERENCES `subscription_types` (`type_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `subscription_types`
--
ALTER TABLE `subscription_types`
  ADD CONSTRAINT `subscription_types_journal_id` FOREIGN KEY (`journal_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `subscription_type_settings`
--
ALTER TABLE `subscription_type_settings`
  ADD CONSTRAINT `subscription_type_settings_type_id` FOREIGN KEY (`type_id`) REFERENCES `subscription_types` (`type_id`) ON DELETE CASCADE;

--
-- Constraints for table `temporary_files`
--
ALTER TABLE `temporary_files`
  ADD CONSTRAINT `temporary_files_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `usage_stats_institution_temporary_records`
--
ALTER TABLE `usage_stats_institution_temporary_records`
  ADD CONSTRAINT `usi_institution_id_foreign` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`institution_id`) ON DELETE CASCADE;

--
-- Constraints for table `usage_stats_total_temporary_records`
--
ALTER TABLE `usage_stats_total_temporary_records`
  ADD CONSTRAINT `ust_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ust_issue_galley_id_foreign` FOREIGN KEY (`issue_galley_id`) REFERENCES `issue_galleys` (`galley_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ust_issue_id_foreign` FOREIGN KEY (`issue_id`) REFERENCES `issues` (`issue_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ust_representation_id_foreign` FOREIGN KEY (`representation_id`) REFERENCES `publication_galleys` (`galley_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ust_submission_file_id_foreign` FOREIGN KEY (`submission_file_id`) REFERENCES `submission_files` (`submission_file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ust_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `usage_stats_unique_item_investigations_temporary_records`
--
ALTER TABLE `usage_stats_unique_item_investigations_temporary_records`
  ADD CONSTRAINT `usii_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usii_representation_id_foreign` FOREIGN KEY (`representation_id`) REFERENCES `publication_galleys` (`galley_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usii_submission_file_id_foreign` FOREIGN KEY (`submission_file_id`) REFERENCES `submission_files` (`submission_file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usii_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `usage_stats_unique_item_requests_temporary_records`
--
ALTER TABLE `usage_stats_unique_item_requests_temporary_records`
  ADD CONSTRAINT `usir_context_id_foreign` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usir_representation_id_foreign` FOREIGN KEY (`representation_id`) REFERENCES `publication_galleys` (`galley_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usir_submission_file_id_foreign` FOREIGN KEY (`submission_file_id`) REFERENCES `submission_files` (`submission_file_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `usir_submission_id_foreign` FOREIGN KEY (`submission_id`) REFERENCES `submissions` (`submission_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_groups`
--
ALTER TABLE `user_groups`
  ADD CONSTRAINT `user_groups_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_group_settings`
--
ALTER TABLE `user_group_settings`
  ADD CONSTRAINT `user_group_settings_user_group_id_foreign` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`user_group_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_group_stage`
--
ALTER TABLE `user_group_stage`
  ADD CONSTRAINT `user_group_stage_context_id` FOREIGN KEY (`context_id`) REFERENCES `journals` (`journal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_group_stage_user_group_id` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`user_group_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_interests`
--
ALTER TABLE `user_interests`
  ADD CONSTRAINT `user_interests_controlled_vocab_entry_id_foreign` FOREIGN KEY (`controlled_vocab_entry_id`) REFERENCES `controlled_vocab_entries` (`controlled_vocab_entry_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_interests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `user_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_user_groups`
--
ALTER TABLE `user_user_groups`
  ADD CONSTRAINT `user_user_groups_user_group_id_foreign` FOREIGN KEY (`user_group_id`) REFERENCES `user_groups` (`user_group_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_user_groups_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
