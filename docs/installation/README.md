---
layout: default
title: Installation
section: installation
permalink: /installation/
---

# Installation

1. Confirm that your server has the following prerequisites installed:
   - Apache 2.4 or higher with mod\_rewrite enabled.
   - PHP 8.5.3 or higher.
   - MySQL 9.4.0 or higher.
2. Clone the repository to the desired location on your server:  
   ```
   git clone https://github.com/dschor5/ECHO.git
   ```
3. Create a MySQL database and import the database `delay.sql` from the root folder where the code was cloned.
4. If you want to run multiple ECHO instances in different folders against the same database (for testing/training alongside a mission instance), edit `delay.sql` before importing it and replace every occurrence of `__TABLE_PREFIX__` with a prefix of your choice (for example `echo_test_`). To use the default single-instance tables, replace `__TABLE_PREFIX__` with a blank string.
5. Rename the file `server-example.inc.php` to `server.inc.php` and update the configuration settings in accordance with the comments embedded within the file and matching your server settings.
6. Modify the `.htaccess` file with the proper `RewriteBase` in accordance with the comments in the file.
7. Navigate to your server's URL to see the **ECHO** home page / login screen.
8. Click login and use the username `admin` and password `secret`. You will immediately be asked to change the password.

**server.inc.php**
Configure the following settings in `server.inc.php`:
- `$server` values to match your server URL and protocol (see inline comments).
- `$database` values (`db_host`, `db_user`, `db_pass`, `db_name`).
- Optional: `$database['table_prefix']` to match the prefix you used in `delay.sql`. Leave it blank if you imported default tables without a prefix.
- `$admin['default_password']` if you want a different default password for new accounts and resets.
