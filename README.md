JUser
=======

A simple Module that glues together ZfcUser, BjyAuthorize, SlmLocale, GoalioRememberMe and Zend\Db. A fork of `manuakasam/SamUser`.


Installation
============

1. Require JTranslate
    ```
    ./composer.phar require jroedel/zf2-juser
    ```
    
2. Copy `config/juser.config.php.dist` to your Application config folder, and customize the values.

3. Create the two tables with the sql in `config/database.sql.dist`.

4. Enable all the modules in your `application.config.php` file (order is important): 
    ```
    <?php
    return [
        'modules' => [
            // ...
	        'ZfcBase',
	        'ZfcUser',
			'BjyAuthorize',
	        'SlmLocale',
			'GoalioRememberMe',
	        'JUser',
        ],
        // ...
    ];
    ```

5. The GUI can be accessed from `/users`. Make sure to double-check that only administers have access to the `juser` routes. This can be configured in your `juser.global.php` file.
