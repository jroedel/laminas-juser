<?php

namespace JUser;

use Laminas\Db\Adapter\Adapter;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\Session;
use Laminas\Session\Storage\SessionArrayStorage;
use JUser\Bridge\Laminas\UserIdRoles;
use JUser\Bridge\Laminas\UserIdRolesFactory;
use JUser\Bridge\Laminas\ZfcUserZendDbPlusSelfAsRole;
use JUser\Bridge\Laminas\ZfcUserZendDbPlusSelfAsRoleFactory;
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
    'bjyauthorize' => [
        'unauthorized_strategy' => Bridge\Laminas\RedirectionStrategy::class,

//         'cache_options'         => [
//                 'adapter'   => [
//                         'name' => 'filesystem',
//                 ],
//                 'plugins'   => [
//                         'Serializer',
//                 ]
//         ],

//         // Key used by the cache for caching the acl
//         'cache_key'             => 'bjyauthorize_acl',

        // set the 'guest' role as default (must be defined in a role provider]
        'default_role' => 'guest',

        /* this module uses a meta-role that inherits from any roles that should
         * be applied to the active user. the identity provider tells us which
         * roles the "identity role" should inherit from.
         *
         * for ZfcUser, this will be your default identity provider
        */
        'identity_provider' => ZfcUserZendDbPlusSelfAsRole::class,

        /* If you only have a default role and an authenticated role, you can
         * use the 'AuthenticationIdentityProvider' to allow/restrict access
         * with the guards based on the state 'logged in' and 'not logged in'.
         *
         * 'default_role'       => 'guest',         // not authenticated
         * 'authenticated_role' => 'user',          // authenticated
         * 'identity_provider'  => 'BjyAuthorize\Provider\Identity\AuthenticationIdentityProvider',
        */

        /* role providers simply provide a list of roles that should be inserted
         * into the Zend\Acl instance. the module comes with two providers, one
         * to specify roles in a config file and one to load roles using a
         * Laminas\Db adapter.
        */
        'role_providers' => [
            /* here, 'guest' and 'user are defined as top-level roles, with
             * 'admin' inheriting from user
            */
            //'BjyAuthorize\Provider\Role\Config' => [
            //        'guest' => [],
            //        'user'  => ['children' => [
            //                'admin' => [],
            //        ]],
            //],
            UserIdRoles::class => [],
            \BjyAuthorize\Provider\Role\LaminasDb::class => [
                'table'                 => 'user_role',
                'identifier_field_name' => 'id',
                'role_id_field'         => 'role_id',
                'parent_role_field'     => 'parent_id',
            ],
        ],
        'guards' => [
            /*
             * The sign-in routes have to be reachable by definition. Anything
             * else this module exposes is guarded in the application config.
             */
            \BjyAuthorize\Guard\Route::class => [
                ['route' => 'zfcuser', 'roles' => ['guest', 'user']],
                ['route' => 'zfcuser/login', 'roles' => ['guest', 'user']],
                ['route' => 'zfcuser/verify', 'roles' => ['guest', 'user']],
                ['route' => 'zfcuser/logout', 'roles' => ['guest', 'user']],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            /*
             * The 'zfcuser*' route names are historical: they used to come from
             * the ZfcUser module. They're kept so that every existing url(),
             * guard and redirect keeps pointing at the right place.
             */
            'zfcuser' => [
                'type' => Literal::class,
                'priority' => 1000,
                'options' => [
                    'route' => '/user',
                    'defaults' => [
                        'controller' => Controller\LoginController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'login' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/login',
                            'defaults' => [
                                'controller' => Controller\LoginController::class,
                                'action'     => 'login',
                            ],
                        ],
                    ],
                    'logout' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/logout',
                            'defaults' => [
                                'controller' => Controller\LoginController::class,
                                'action'     => 'logout',
                            ],
                        ],
                    ],
                    //where the emailed magic link lands: /user/verify?token=...
                    'verify' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/verify',
                            'defaults' => [
                                'controller' => Controller\LoginController::class,
                                'action'     => 'verify',
                            ],
                        ],
                    ],
                ],
            ],
            /*
             * **The user-administration surface has no laminas controller.** All seven of
             * these routes are served by the host application's Symfony kernel as of
             * 2026-08-21 — `App\Controller\UsersController`, `UserCreateController`,
             * `UserEditController`, `UserDeleteController` and `ApiTokensController`, over
             * `App\JUser\UserAdmin` — and `JUser\Controller\UsersController` was deleted
             * with them.
             *
             * **The routes themselves stay, and are not vestigial.** They are how a Twig
             * template addresses a page (`laminas_path('juser/user/edit', …)`), and
             * BjyAuthorize's guards are keyed by route name — this module's own guards cover
             * only the sign-in routes, so the seven `administrator` entries live in the host
             * application's config/autoload/juser.global.php and name these. What is gone is
             * `'controller' => …`, because there is no longer one to name; the `action`
             * defaults are left as documentation of what each path does.
             *
             * A request that reached laminas for one of these paths would fail to dispatch,
             * and that is the truthful answer rather than a gap: the pages exist only on the
             * Symfony side. Same shape as the Books module's `library-imports` tree, which
             * did this first on 2026-08-18.
             *
             * The `juser/user` segment is `may_terminate => false` and has been since
             * `juser/user/show` was retired on 2026-08-20. It does not merely fail to match
             * `/users/5` — it cannot be *assembled* either: `Part::assemble()` throws
             * `Part route may not terminate` rather than returning a URL nothing serves. The
             * `user` entity spec below named it as `show_route` until 2026-08-21, which is
             * why that field is now absent; see the comment there.
             */
            'juser' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/users',
                    'defaults' => [
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'user' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:user_id',
                            'constraints' => [
                                'user_id' => '[0-9]{1,5}',
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'edit' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                    ],
                                ],
                            ],
                            'delete' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action'     => 'delete',
                                    ],
                                ],
                            ],
                            /*
                             * API token management. A page of its own rather than a
                             * panel on /edit, for two reasons: /edit is one big form
                             * and the issue/revoke controls are forms too, which
                             * cannot legally nest; and issuing a credential should
                             * not share a submit button with renaming somebody.
                             *
                             * Both are guarded to administrators in the application's
                             * config/autoload/juser.global.php — JUser's own guards
                             * cover only the sign-in routes.
                             */
                            'api-tokens' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/api-tokens',
                                    'defaults' => [
                                        'action'     => 'apiTokens',
                                    ],
                                ],
                            ],
                            'api-token-revoke' => [
                                'type'    => Segment::class,
                                'options' => [
                                    'route'    => '/api-tokens/:token_id/revoke',
                                    'constraints' => [
                                        'token_id' => '[0-9]+',
                                    ],
                                    'defaults' => [
                                        'action'     => 'revokeApiToken',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'create' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'create-role' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/roles/create',
                            'defaults' => [
                                'action'     => 'createRole',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    /*
     * **No `controllers`, no `controller_plugins`, no `view_manager`, as of 2026-08-21.**
     * This module registers nothing with laminas-mvc any more: `LoginController` was the
     * last `AbstractActionController` here and it is gone, with its factory and its four
     * view scripts. `view/` no longer exists, so neither does the template map.
     *
     * The `zfcUserAuthentication` controller plugin went with it. It only ever wrapped the
     * same AuthenticationService, and with no laminas-mvc controllers left in this module
     * there was nothing to register it for; its two consumers in the host application use
     * `identity()` from laminas-mvc-plugin-identity, whose factory resolves
     * `Laminas\Authentication\AuthenticationService` — which the aliases below point at
     * this module's own service, so the answer is identical.
     *
     * What is still declared: the routes (a name is what `url()` and a BjyAuthorize guard
     * entry address, and both still name these), the view helpers (a host layout that has
     * not been ported still calls `zfcUserDisplayName`), the services, and the session
     * configuration.
     */
    'view_helpers' => [
        'invokables' => [
        ],
        'factories' => [
            Bridge\Laminas\ZfcUserDisplayName::class => Bridge\Laminas\ZfcUserViewHelperFactory::class,
            Bridge\Laminas\ZfcUserIdentity::class    => Bridge\Laminas\ZfcUserViewHelperFactory::class,
        ],
        'aliases' => [
            //historical names, kept so existing templates keep working
            'zfcUserDisplayName' => Bridge\Laminas\ZfcUserDisplayName::class,
            'zfcUserIdentity'    => Bridge\Laminas\ZfcUserIdentity::class,
        ],
    ],
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
            ZfcUserZendDbPlusSelfAsRole::class => ZfcUserZendDbPlusSelfAsRoleFactory::class,
            UserIdRoles::class              => UserIdRolesFactory::class,
            'JUser\AuthService'             => Bridge\Laminas\AuthenticationServiceFactory::class,
            Bridge\Laminas\UserService::class => Bridge\Laminas\UserServiceFactory::class,
            Service\LoginTokenService::class => Service\LoginTokenServiceFactory::class,
            Model\ApiTokenTable::class      => Service\ApiTokenTableFactory::class,
            Service\ApiTokenService::class  => Service\ApiTokenServiceFactory::class,
            ActingUserProviderInterface::class => Bridge\Laminas\AuthServiceActingUserProviderFactory::class,
        ],
        'invokables'  => [
            Bridge\Laminas\RedirectionStrategy::class => Bridge\Laminas\RedirectionStrategy::class,
        ],
        'aliases' => [
            \Laminas\Session\SessionManager::class => Session\ManagerInterface::class,
            //historical service names, kept so existing consumers keep working
            'zfcuser_user_mapper'           => Model\UserTable::class,
            'zfcuser_auth_service'          => 'JUser\AuthService',
            'zfcuser_user_service'          => Bridge\Laminas\UserService::class,
            'zfcuser_zend_db_adapter'       => Adapter::class,
            \Laminas\Authentication\AuthenticationService::class => 'JUser\AuthService',
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
