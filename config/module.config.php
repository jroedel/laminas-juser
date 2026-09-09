<?php

namespace JUser;

use Laminas\Db\Adapter\Adapter;
use Laminas\Session;
use Laminas\Session\Storage\SessionArrayStorage;
use SionModel\Service\ActingUserProviderInterface;

return [
    'juser' => [
        //service name of the Laminas\Db adapter this module works against
        'db_adapter' => Adapter::class,

        //where to land after signing in / after signing out
        'login_redirect_route' => 'welcome',
        'logout_redirect_route' => 'zfcuser/login',

        //how long an emailed magic link stays valid (ISO 8601 duration)
        'web_verification_token_expiration_interval' => 'PT15M',

        //how long an issued JWT lasts (ISO 8601 duration). Six months, which is
        //what the retired LoginV1ApiController hardcoded for years behind a
        ////@todo, and the value this kept when that controller went away. Long
        //because the clients are unattended agents: nothing can re-authenticate
        //on their behalf, so a short lifetime does not buy security, it buys
        //outages. Revocation, not expiry, is the control that matters here.
        'api_jwt_lifetime' => 'P6M',

        //roles whose holders an administrator may mint a token for from the users
        //screen. EMPTY BY DEFAULT, which switches the issue button off entirely.
        //Naming a role here is what turns the feature on, and it should never be
        //a role ordinary registration grants — see the application's
        //config/autoload/juser.global.php for the reasoning at this site.
        'api_token_roles' => [],
    ],
    // Authorization data the application's own engine (App\Acl in schoenstatt.link) reads:
    // the anonymous default role and this module's own sign-in route guards. BjyAuthorize is
    // gone; 'BjyAuthorize\Guard\Route' is a legacy identifier string the assembler keys on.
    'bjyauthorize' => [
        'default_role' => 'guest',
        'guards' => [
            /*
             * The sign-in routes have to be reachable by definition. Anything
             * else this module exposes is guarded in the application config.
             */
            'BjyAuthorize\Guard\Route' => [
                ['route' => 'zfcuser', 'roles' => ['guest', 'user']],
                ['route' => 'zfcuser/login', 'roles' => ['guest', 'user']],
                ['route' => 'zfcuser/verify', 'roles' => ['guest', 'user']],
                ['route' => 'zfcuser/logout', 'roles' => ['guest', 'user']],
            ],
        ],
    ],
    /*
     * **No `controllers`, no `controller_plugins`, no `view_manager`, as of 2026-08-21.**
     * This module registers nothing with laminas-mvc any more: `LoginController` was the
     * last `AbstractActionController` here and it is gone, with its factory and its four
     * view scripts. `view/` no longer exists, so neither does the template map.
     *
     * The `zfcUserAuthentication` controller plugin went with it, and so, in 2026-09, did
     * the whole `JUser\Bridge\Laminas` namespace: the authentication service, its session
     * storage, the two `zfcUser*` view helpers and the ZfcUser service shim. Who is signed
     * in is now {@see \JUser\Host\IdentityInterface}, and this module ships one
     * implementation of it, {@see \JUser\Authentication\SessionIdentity}, which needs the
     * host's session and this module's user table. `laminas-authentication` leaves
     * `require` with it.
     *
     * No view helpers are declared any more. They existed for a laminas host's layout, and
     * the rule the display-name one carried is {@see \JUser\Model\DisplayName}, which any
     * renderer can call.
     *
     * What is still declared: the routes (a name is what `url()` and a guard entry address,
     * and both still name these), the services, and the session configuration.
     */
    'service_manager' => [
        'factories' => [
            Model\UserTable::class          => Service\UserTableFactory::class,
            Form\EditUserForm::class        => Service\EditUserFormFactory::class,
            Form\CreateRoleForm::class      => Service\CreateRoleFormFactory::class,
            'JUser\Config'                  => Service\ConfigServiceFactory::class,
            'JUser\Cache'                   => Service\CacheFactory::class,
            // Configures the default SessionManager instance
            Session\ManagerInterface::class => Session\Service\SessionManagerFactory::class,
            // Provides session configuration to SessionManagerFactory
            Session\Config\ConfigInterface::class => Session\Service\SessionConfigFactory::class,
            Service\Mailer::class           => Service\MailerFactory::class,
            Service\LoginTokenService::class => Service\LoginTokenServiceFactory::class,
            Model\ApiTokenTable::class      => Service\ApiTokenTableFactory::class,
            Service\ApiTokenService::class  => Service\ApiTokenServiceFactory::class,
            //Who is signed in. The host may replace this with its own implementation of
            //the interface; what it must not do is keep a second identity beside it.
            Host\IdentityInterface::class    => Authentication\SessionIdentityFactory::class,
            ActingUserProviderInterface::class => Service\IdentityActingUserProviderFactory::class,
        ],
        'aliases' => [
            \Laminas\Session\SessionManager::class => Session\ManagerInterface::class,
            //historical service names, kept so existing consumers keep working
            'zfcuser_user_mapper'           => Model\UserTable::class,
            'zfcuser_zend_db_adapter'       => Adapter::class,
        ],

    ],
    'session_storage' => [
        'type' => SessionArrayStorage::class
    ],
    'session_config' => [
        // Set the session and cookie expiries to 30 days
        'cache_expire' => 30 * 24 * 60 * 60,
        'cookie_lifetime' => 30 * 24 * 60 * 60,
        'gc_maxlifetime'      => 30 * 24 * 60 * 60,
//         'cookie_secure' => true,
    ],
    'sion_model' => [
        'entities' => [
            'user' => [
                'name'                                  => 'user',
                'table_name'                            => 'user',
                'table_key'                             => 'user_id',
                'entity_key_field'                      => 'userId',
                'sion_model_class'                      => Model\UserTable::class,
                //Empty since 2026-08-21: the class is gone. SionModel reads this to
                //decide which controllers its own SionControllerFactory should build, and
                //JUser's never was one — it had an explicit factory — so nothing changes
                //behaviourally. A string naming a deleted class does not throw here
                //(nothing autoloads it), which is exactly why it would have gone unnoticed.
                'sion_controllers'                      => [],
                'controller_services'                   => [
                ],
                'row_processor_function'                => 'processUserRow',
                'depends_on_entities'                   => ['user-role', 'user-role-link'],
//                 'get_object_function'                   => 'getSimpleAssociationBySwId',
//                 'get_objects_function'                  => 'getAssociations',
                'name_field'                            => 'username',
                'name_field_is_translateable'           => false,
//                 'format_view_helper'                    => 'formatEntity',
//                 'country_field'                         => 'country',
//                 'report_changes'                        => true,
                'required_columns_for_creation'         => [ //required for creation
                    'username',
                    'email',
                    'displayName',
                ],
                'index_route'                           => 'juser',
//                 'index_template'                        => 'project/events/index',
                'default_route_key'                     => 'user_id',
                /*
                 * **No `show_route`, deliberately.** There is no page showing one account:
                 * `juser/user` exists only to carry /edit, /delete and /api-tokens, and
                 * `juser/user/show` was retired 2026-08-20 because its action did not
                 * exist. This spec named `juser/user` anyway until 2026-08-21, and the
                 * consequence was not a wrong URL: a `may_terminate => false` part route
                 * does not assemble at all, it throws `Part route may not terminate`. So
                 * anything formatting a `user` entity as a link raised a 500.
                 *
                 * Nothing does today — `report_changes` is off for this entity and
                 * `sch_changes` holds no `user` row — and an anonymous caller was shielded
                 * by a second accident: `route/juser/user` *is* an ACL resource, so
                 * `isActionAllowed('show')` refused before the assemble could throw. An
                 * administrator, who is allowed, would have got the exception. Omitting the
                 * route is what makes the name render as plain text, which is the right
                 * rendering for an entity with no page of its own.
                 */
                'edit_action_form'                      => Form\EditUserForm::class,
//                 'edit_action_template'                   => 'project/events/edit',
                /*
                 * `juser/user/edit`, not `association-edit`. These route names were
                 * copy-pasted from the association entity when this spec was written and
                 * never corrected — assembling `association-edit` with a `user_id` throws
                 * `Missing parameter "sw_id"`. The *keys* were always right; only the route
                 * names belonged to another entity. The neighbouring `user-role` spec
                 * carried the same copy-paste in `required_columns_for_creation`, and that
                 * one was not dead: it broke `/users/roles/create` outright, for years
                 * (fixed 2026-08-21).
                 */
                'edit_route'                            => 'juser/user/edit',
                'edit_route_key'                        => 'user_id',
                'edit_route_key_field'                  => 'userId',
                'create_action_form'                    => Form\EditUserForm::class,
//                 'create_action_valid_data_handler'      => 'createAssociation',
                /*
                 * `juser`, the index — the same destination as
                 * `delete_action_redirect_route` below and what
                 * `App\Controller\UserCreateController` actually redirects to. It takes no
                 * parameter, hence no key fields.
                 */
                'create_action_redirect_route'          => 'juser',
//                 'create_action_template'                   => 'project/events/create',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'             => 'delete_event',
                'delete_action_redirect_route'          => 'juser',
//                 'database_bound_data_preprocessor'      => 'associationPreprocessor',
                 'database_bound_data_postprocessor'     => 'userPostprocessor',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'             => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'many_to_one_update_columns'            => [
                ],
                'update_columns' => [
                    'userId'            => 'user_id',
                    'username'          => 'username',
                    'email'             => 'email',
                    'displayName'       => 'display_name',
                    'createdOn'         => 'create_datetime',
                    'createdBy'         => 'create_by',
                    'updatedOn'         => 'update_datetime',
                    'updatedBy'         => 'update_by',
                    'emailVerified'     => 'email_verified',
                    'isMultiPersonUser' => 'multi_person_user',
                    'verificationToken' => 'verification_token',
                    'verificationExpiration' => 'verification_expiration',
                    'active'            => 'state',
//                  'languages'         => $this->filterDbArray($row['lang'], ';'),
                    'personId'          => 'PersID',
                ],
            ],
            'user-role' => [
                'name'                                  => 'user-role',
                'table_name'                            => 'user_role',
                'table_key'                             => 'id',
                'entity_key_field'                      => 'roleId',
                'sion_model_class'                      => Model\UserTable::class,
                //Empty since 2026-08-21: the class is gone. SionModel reads this to
                //decide which controllers its own SionControllerFactory should build, and
                //JUser's never was one — it had an explicit factory — so nothing changes
                //behaviourally. A string naming a deleted class does not throw here
                //(nothing autoloads it), which is exactly why it would have gone unnoticed.
                'sion_controllers'                      => [],
                'controller_services'                   => [
                ],
                'row_processor_function'                => 'processRoleRow',
//                 'get_object_function'                   => 'getSimpleAssociationBySwId',
//                 'get_objects_function'                  => 'getAssociations',
                'name_field'                            => 'name',
                'name_field_is_translateable'           => false,
//                 'format_view_helper'                    => 'formatEntity',
//                 'country_field'                         => 'country',
//                 'report_changes'                        => true,
                /*
                 * **The role's own required column, and nothing else.**
                 *
                 * This said `username`, `email`, `displayName` until 2026-08-21 —
                 * copy-pasted from the `user` entity above — so
                 * `createEntity('user-role', …)` threw
                 * `InvalidArgumentException: … Missing \`username\`` on **every**
                 * submission, and /users/roles/create had never once created a role. The
                 * form validated, the write refused, and the page said "Error in form
                 * submission, please review." about a form that was fine; JUser 2.0's
                 * writeFailure() message is what finally made the failure legible, and
                 * porting the route to Symfony is what made it testable
                 * (test/Smoke/JUserAdminSmokeTest in the application repo).
                 *
                 * `name` maps to `user_role.role_id`, which is the table's only NOT NULL
                 * column without a default; `is_default` defaults to 0 and `parent_id` is
                 * nullable.
                 */
                'required_columns_for_creation'         => [
                    'name',
                ],
                'index_route'                           => 'juser',
//                 'index_template'                        => 'project/events/index',
//                 'default_route_key'                     => 'user_id',
//                 'show_route'                            => 'juser/user',
//                 'show_route_key'                        => 'user_id',
//                 'show_route_key_field'                  => 'userId',
//                 'edit_action_form'                      => Form\EditUserForm::class,
//                 'edit_action_template'                   => 'project/events/edit',
//                 'edit_route'                            => 'association-edit',
//                 'edit_route_key'                        => 'user_id',
//                 'edit_route_key_field'                  => 'userId',
                'create_action_form'                    => Form\CreateRoleForm::class,
//                 'create_action_valid_data_handler'      => 'createAssociation',
                'create_action_redirect_route'          => 'juser',
//                 'create_action_redirect_route_key'      => 'user_id',
//                 'create_action_redirect_route_key_field'=> 'userId',
//                 'create_action_template'                   => 'project/events/create',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'             => 'delete_event',
                'delete_action_redirect_route'          => 'juser',
//                 'database_bound_data_preprocessor'      => 'associationPreprocessor',
//                 'database_bound_data_postprocessor'     => 'associationPostprocessor',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'             => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'many_to_one_update_columns'            => [
                ],
                'update_columns' => [
                    'roleId'            => 'id',
                    'name'              => 'role_id',
                    'isDefault'         => 'is_default',
                    'parentId'          => 'parent_id',
                    'createdOn'         => 'create_datetime',
                    'createdBy'         => 'create_by',
                ],
            ],
            'user-role-link' => [
                'name'                                  => 'user-role-link',
                'table_name'                            => 'user_role_linker',
                'table_key'                             => 'id',
                'entity_key_field'                      => 'linkId',
                'sion_model_class'                      => Model\UserTable::class,
//                 //Empty since 2026-08-21: the class is gone. SionModel reads this to
                //decide which controllers its own SionControllerFactory should build, and
                //JUser's never was one — it had an explicit factory — so nothing changes
                //behaviourally. A string naming a deleted class does not throw here
                //(nothing autoloads it), which is exactly why it would have gone unnoticed.
                'sion_controllers'                      => [],
//                 'controller_services'                   => [
//                 ],
                'row_processor_function'                => 'processUserRoleLinkerRow',
                'depends_on_entities'                   => ['user-role', 'user'],
//                 'get_object_function'                   => 'getSimpleAssociationBySwId',
//                 'get_objects_function'                  => 'getAssociations',
                'name_field'                            => 'roleName',
                'name_field_is_translateable'           => false,
//                 'format_view_helper'                    => 'formatEntity',
//                 'country_field'                         => 'country',
//                 'report_changes'                        => true,
                'required_columns_for_creation'         => [ //required for creation
                    'userId',
                    'roleId',
                ],
                'index_route'                           => 'juser',
//                 'index_template'                        => 'project/events/index',
//                 'default_route_key'                     => 'user_id',
//                 'show_route'                            => 'juser/user',
//                 'show_route_key'                        => 'user_id',
//                 'show_route_key_field'                  => 'userId',
//                 'edit_action_form'                      => Form\EditUserForm::class,
//                 'edit_action_template'                   => 'project/events/edit',
//                 'edit_route'                            => 'association-edit',
//                 'edit_route_key'                        => 'user_id',
//                 'edit_route_key_field'                  => 'userId',
                'create_action_form'                    => Form\CreateRoleForm::class,
//                 'create_action_valid_data_handler'      => 'createAssociation',
                'create_action_redirect_route'          => 'juser',
//                 'create_action_redirect_route_key'      => 'user_id',
//                 'create_action_redirect_route_key_field'=> 'userId',
//                 'create_action_template'                   => 'project/events/create',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'             => 'delete_event',
                'delete_action_redirect_route'          => 'juser',
//                 'database_bound_data_preprocessor'      => 'associationPreprocessor',
//                 'database_bound_data_postprocessor'     => 'associationPostprocessor',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'             => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'many_to_one_update_columns'            => [
                ],
                'update_columns' => [
                    'linkId'            => 'id',
                    'userId'            => 'user_id',
                    'roleId'            => 'role_id',
                    'createdOn'         => 'create_datetime',
                    'createdBy'         => 'create_by',
                ],
            ],
        ],
    ],
];
