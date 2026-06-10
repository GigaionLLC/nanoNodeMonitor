<?php

// the project version
define('PROJECT_VERSION', '1.9.0');

// project URL
define('PROJECT_URL', 'https://github.com/gigaion/nanoNodeMonitor');

// URL to get version of latest release from github
define('GITHUB_LATEST_API_URL', 'https://api.github.com/repos/gigaion/nanoNodeMonitor/releases/latest');

// nano rep account for Nano Node Monitor 
define ('NODEMON_REP_ACCOUNT', 'nano_11pb5aa6uirs9hoqsg4swnzyehoiqowj94kdpthwkhwufmtd6a11xx35iron');

// banano rep account for Nano Node Monitor 
define ('NODEMON_BAN_REP_ACCOUNT', 'nano_11pb5aa6uirs9hoqsg4swnzyehoiqowj94kdpthwkhwufmtd6a11xx35iron');

// nano donation account for Nano Node Monitor development
define ('NODEMON_DON_ACCOUNT', 'nano_11pb5aa6uirs9hoqsg4swnzyehoiqowj94kdpthwkhwufmtd6a11xx35iron');

// baano donation account for Nano Node Monitor development
define ('NODEMON_BAN_DON_ACCOUNT', 'nano_11pb5aa6uirs9hoqsg4swnzyehoiqowj94kdpthwkhwufmtd6a11xx35iron');

// total number of characters for displaying Nano addresses including ellipsis
define ('NANO_ADDR_NUM_CHAR', 17);

// curl timeout in seconds to receive data from external services (max delay is EXTERNAL_TIMEOUT + EXTERNAL_CONECTTIMEOUT)
define ('EXTERNAL_TIMEOUT', 3);

// curl timeout in seconds to connect to external services (max delay is EXTERNAL_TIMEOUT + EXTERNAL_CONECTTIMEOUT)
define ('EXTERNAL_CONECTTIMEOUT', 2);

// maximum allowed age of data to be part of the block confirmation time percentiles calculation (milliseconds)
define ('CONFIRMATION_TIME_LIMIT', 600000);
