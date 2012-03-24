CREATE TABLE `2checkout` (
  `2co_id` int(11) NOT NULL auto_increment,
  `start_time` datetime NOT NULL default '0000-00-00 00:00:00',
  `finish_time` datetime NOT NULL default '0000-00-00 00:00:00',
  `status` varchar(50) collate latin1_general_ci NOT NULL default '',
  `amount` float NOT NULL default '0',
  `2co_order_id` int(11) NOT NULL default '0',
  `session_id` varchar(50) collate latin1_general_ci NOT NULL default '',
  PRIMARY KEY  (`2co_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;