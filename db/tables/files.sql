/**
 * files.sql
 * 
 * Files table definition
 * 
 * @author    Belikhun
 * @since     2.0.0
 * @license   https://tldrlegal.com/license/mit-license MIT
 * 
 * Copyright (C) 2018-2023 Belikhun. All right reserved
 * See LICENSE in the project root for license information.
 */

CREATE TABLE files (
	id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
	hash TEXT NOT NULL,
	filename TEXT NOT NULL,
	extension TEXT NOT NULL,
	mimetype TEXT NOT NULL,
	size INTEGER DEFAULT 0 NOT NULL,
	author INTEGER,
	created INTEGER NOT NULL
);
