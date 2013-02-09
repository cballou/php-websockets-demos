CREATE TABLE `ws_todo` (
    `id` int(10) unsigned NOT NULL auto_increment,
    `position` int(10) unsigned NOT NULL default 0,
    `text` varchar(255) collate utf8_unicode_ci NOT NULL default '',
    `created_ts` timestamp NOT NULL default CURRENT_TIMESTAMP,
    PRIMARY KEY  (`id`),
    KEY `position` (`position`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `ws_todo` VALUES (1, 0, 'My First Todo');
