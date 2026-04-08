CREATE TABLE tx_alice_analysis (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) DEFAULT '0' NOT NULL,
    crdate int(11) DEFAULT '0' NOT NULL,
    results mediumtext,
    score int(11) DEFAULT '0' NOT NULL,
    lcp float DEFAULT '0' NOT NULL,
    cls float DEFAULT '0' NOT NULL,
    inp float DEFAULT '0' NOT NULL,
    status int(11) DEFAULT '0' NOT NULL,
    errors int(11) DEFAULT '0' NOT NULL,
    warnings int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)
);
