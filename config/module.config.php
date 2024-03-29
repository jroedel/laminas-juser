<?php

declare(strict_types=1);

namespace JUser;

use BjyAuthorize\Provider\Role\LaminasDb;
use JUser\Provider\Identity\LmcUserZendDbPlusSelfAsRole;
use JUser\Provider\Role\UserIdRoles;
use JUser\Service\UserIdRolesFactory;
use Laminas\Db\Adapter\Adapter;
use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Segment;
use Laminas\ServiceManager\Proxy\LazyServiceFactory;
use Laminas\Session;
use Laminas\Session\SessionManager;
use Laminas\Session\Storage\SessionArrayStorage;
use LmcUser\Authentication\Adapter\Db;
use LmcUser\Form\LoginFilter;

use const PHP_EOL;

return [
    'lmcuser'         => [
        'lmcuser_laminas_db_adapter' => Adapter::class,
        // telling LmcUser to use our own class
        'user_entity_class'                 => Model\User::class,
        'auth_adapters'                     => [
            100 => Db::class,
        ],
        'enable_default_entities'           => false,
        'enable_registration'               => true,
        'use_registration_form_captcha'     => true,
        'form_captcha_options'              => [
            'class'   => 'figlet',
            'options' => [
                'wordLen'    => 5,
                'expiration' => 300,
                'timeout'    => 300,
            ],
        ],
        'enable_display_name'               => true,
        'enable_username'                   => true,
        'auth_identity_fields'              => ['username', 'email'],
        'login_redirect_route'              => 'home',
        'logout_redirect_route'             => 'home',
        'use_redirect_parameter_if_present' => true,
        'enable_user_state'                 => true,
        //the user state will stay at 0 until the user has been validated
        'default_user_state'              => 0,
        'allowed_login_states'            => [1],
        'user_login_widget_view_template' => 'lmc-user/user/login',
    ],
    'juser'           => [
        'verification_email_message'                 => [
            'sender'  => 'juser@example.com',
            'subject' => 'Sign in verification',
            'body'    => 'Thanks for signing up!, Please enter the following code into the app where you are '
            . 'signing in:' . PHP_EOL . PHP_EOL . '%s' . PHP_EOL . PHP_EOL
            . 'If you did not request a login, please ignore this message. '
            . 'You will never be contacted directly for this code. Thanks!',
        ],
        'api_verification_token_sent_response_text'  => 'Hang in there champ, you\'ll be getttin that email.',
        'api_verification_token_length'              => 6,
        'api_verification_token_expiration_interval' => 'P1D',
        'jwt_id_length'                              => 10,
        'jwt_id_charlist'                            => null, //null means base64 charset
        'jwt_expiration_interval'                    => 'P6M', //6 months
    ],
    'bjyauthorize'    => [
        'unauthorized_strategy' => View\RedirectionStrategy::class,

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
         * for LmcUser, this will be your default identity provider
        */
        'identity_provider' => LmcUserZendDbPlusSelfAsRole::class,

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
            LaminasDb::class   => [
                'table'                 => 'user_role',
                'identifier_field_name' => 'id',
                'role_id_field'         => 'role_id',
                'parent_role_field'     => 'parent_id',
            ],
        ],
    ],
    'router'          => [
        'routes' => [
            'api-v1-login'                         => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/api/v1/users/login',
                    'defaults' => [
                        'action'     => 'login',
                        'controller' => Controller\LoginV1ApiController::class,
                    ],
                ],
            ],
            'api-v1-login-with-verification-token' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/api/v1/users/login-with-verification-token',
                    'defaults' => [
                        'action'     => 'loginWithVerificationToken',
                        'controller' => Controller\LoginV1ApiController::class,
                    ],
                ],
            ],
            'api-v1-request-verification-token'    => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/api/v1/users/request-verification-token',
                    'defaults' => [
                        'action'     => 'requestVerificationToken',
                        'controller' => Controller\LoginV1ApiController::class,
                    ],
                ],
            ],
            'juser'                                => [
                'type'          => Literal::class,
                'options'       => [
                    'route'    => '/users',
                    'defaults' => [
                        'controller' => Controller\UsersController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes'  => [
                    'verify-email' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/verify-email',
                            'defaults' => [
                                'action' => 'verifyEmail',
                            ],
                        ],
                    ],
                    'thanks'       => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/thanks',
                            'defaults' => [
                                'action' => 'thanks',
                            ],
                        ],
                    ],
                    'user'         => [
                        'type'          => Segment::class,
                        'options'       => [
                            'route'       => '/:user_id',
                            'constraints' => [
                                'user_id' => '[0-9]{1,5}',
                            ],
                            'defaults'    => [
                                'controller' => Controller\UsersController::class,
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes'  => [
                            'edit'            => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action' => 'edit',
                                    ],
                                ],
                            ],
                            'delete'          => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action' => 'delete',
                                    ],
                                ],
                            ],
                            'change-password' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/change-password',
                                    'defaults' => [
                                        'action' => 'change-password',
                                    ],
                                ],
                            ],
                            'show'            => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/show',
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'create'       => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'controller' => Controller\UsersController::class,
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'create-role'  => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/roles/create',
                            'defaults' => [
                                'controller' => Controller\UsersController::class,
                                'action'     => 'createRole',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'controllers'     => [
        'factories' => [
            Controller\UsersController::class      => Service\UsersControllerFactory::class,
            Controller\LoginV1ApiController::class => Service\LoginV1ApiControllerFactory::class,
        ],
    ],
    'view_manager'    => [
        'template_map'        => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            'users' => __DIR__ . '/../view',
        ],
    ],
    'view_helpers'    => [
        'invokables' => [
            'userWithIp' => View\Helper\UserWithIp::class,
        ],
        'factories'  => [],
    ],
    'service_manager' => [
        'factories'     => [
            Model\UserTable::class              => Service\UserTableFactory::class,
            Form\EditUserForm::class            => Service\EditUserFormFactory::class,
            Form\CreateRoleForm::class          => Service\CreateRoleFormFactory::class,
            Form\ChangeOtherPasswordForm::class => Service\ChangeOtherPasswordFormFactory::class,
            'JUser\Config'                      => Service\ConfigServiceFactory::class,
            'JUser\Cache'                       => Service\CacheFactory::class,
            Service\Mailer::class               => Service\MailerFactory::class,
            LmcUserZendDbPlusSelfAsRole::class  => Service\LmcUserLaminasDbPlusSelfAsRoleFactory::class,
            UserIdRoles::class                  => UserIdRolesFactory::class,
            Authentication\Adapter\CredentialOrTokenQueryParams::class
                => Service\CredentialOrTokenQueryParamsFactory::class,
            Authentication\Adapter\Jwt::class    => Service\JwtFactory::class,
            LoginFilter::class                   => Service\LoginFilterFactory::class,
            Filter\HashPasswordForLmcUser::class => Service\HashPasswordForLmcUserFactory::class,
        ],
        'invokables'    => [
            View\RedirectionStrategy::class => View\RedirectionStrategy::class,
        ],
        'lazy_services' => [
            // Mapping services to their class names is required
            // since the ServiceManager is not a declarative DIC.
            'class_map' => [
                Form\CreateRoleForm::class           => Form\CreateRoleForm::class,
                Form\EditUserForm::class             => Form\EditUserForm::class,
                Service\Mailer::class                => Service\Mailer::class,
                Filter\HashPasswordForLmcUser::class => Filter\HashPasswordForLmcUser::class,
            ],
        ],
        'delegators'    => [
            Filter\HashPasswordForLmcUser::class => [LazyServiceFactory::class],
            Form\CreateRoleForm::class           => [LazyServiceFactory::class],
            Form\EditUserForm::class             => [LazyServiceFactory::class],
            Service\Mailer::class                => [LazyServiceFactory::class],
            'lmcuser_register_form'              => [Service\RegisterFormDelegateFactory::class],
        ],
        'aliases'       => [
            'BjyAuthorize\Cache'  => 'JUser\Cache',
            SessionManager::class => Session\ManagerInterface::class,
            'lmcuser_user_mapper' => Model\UserTable::class,
            /*
             * Since we require SionModel, and they provide us a Logger,
             * we just latch on to theirs. This way consumers of this module
             * can always change this alias to their own instance of
             * Laminas\Log\LoggerInterface if they want a different one.
             */
            'JUser\Logger' => 'SionModel\Logger',
        ],
    ],
    'session_storage' => [
        'type' => SessionArrayStorage::class,
    ],
    'session_config'  => [
        // Set the session and cookie expires to 30 days
        'cache_expire'    => 30 * 24 * 60 * 60,
        'cookie_lifetime' => 30 * 24 * 60 * 60,
        'gc_maxlifetime'  => 30 * 24 * 60 * 60,
//         'cookie_secure' => true,
    ],
    'sion_model'      => [
        'entities' => [
            'user'           => [
                'name'                   => 'user',
                'table_name'             => 'user',
                'table_key'              => 'user_id',
                'entity_key_field'       => 'userId',
                'sion_model_class'       => Model\UserTable::class,
                'row_processor_function' => 'processUserRow',
                'depends_on_entities'    => ['user-role', 'user-role-link'],
//                 'get_object_function'                   => 'getSimpleAssociationBySwId',
//                 'get_objects_function'                  => 'getAssociations',
                'name_field'                        => 'username',
                'name_field_is_translatable'        => false,
                'required_columns_for_creation'     => [ //required for creation
                    'username',
                    'email',
                    'displayName',
                    'password',
                ],
                'default_route_params'              => ['user_id' => 'userId'],
                'index_route'                       => 'juser',
                'show_route'                        => 'juser/user',
                'edit_action_form'                  => Form\EditUserForm::class,
                'edit_route'                        => 'association-edit',
                'create_action_form'                => Form\EditUserForm::class,
                'create_action_redirect_route'      => 'association',
                'enable_delete_action'              => true,
                'delete_action_redirect_route'      => 'juser',
                'database_bound_data_postprocessor' => 'userPostprocessor',
                'many_to_one_update_columns'        => [],
                'update_columns'                    => [
                    'userId'                 => 'user_id',
                    'username'               => 'username',
                    'email'                  => 'email',
                    'displayName'            => 'display_name',
                    'password'               => 'password',
                    'createdOn'              => 'create_datetime',
                    'createdBy'              => 'create_by',
                    'updatedOn'              => 'update_datetime',
                    'updatedBy'              => 'update_by',
                    'emailVerified'          => 'email_verified',
                    'mustChangePassword'     => 'must_change_password',
                    'isMultiPersonUser'      => 'multi_person_user',
                    'verificationToken'      => 'verification_token',
                    'verificationExpiration' => 'verification_expiration',
                    'active'                 => 'state',
//                  'languages'         => $this->filterDbArray($row['lang'], ';'),
                    'personId' => 'PersID',
                ],
            ],
            'user-role'      => [
                'name'                   => 'user-role',
                'table_name'             => 'user_role',
                'table_key'              => 'id',
                'entity_key_field'       => 'roleId',
                'sion_model_class'       => Model\UserTable::class,
                'row_processor_function' => 'processRoleRow',
//                 'get_object_function'                   => 'getSimpleAssociationBySwId',
//                 'get_objects_function'                  => 'getAssociations',
                'name_field'                 => 'name',
                'name_field_is_translatable' => false,
//                 'format_view_helper'                    => 'formatEntity',
//                 'country_field'                         => 'country',
//                 'report_changes'                        => true,
                'required_columns_for_creation' => [ //required for creation
                    'username',
                    'email',
                    'displayName',
                    'password',
                ],
                'index_route'                   => 'juser',
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
                'create_action_form' => Form\CreateRoleForm::class,
//                 'create_action_valid_data_handler'      => 'createAssociation',
                'create_action_redirect_route' => 'juser',
//                 'create_action_redirect_route_key'      => 'user_id',
//                 'create_action_redirect_route_key_field'=> 'userId',
//                 'create_action_template'                   => 'project/events/create',
                'enable_delete_action' => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'             => 'delete_event',
                'delete_action_redirect_route' => 'juser',
//                 'touch_default_field'                   => 'eventId',
//                 'touch_field_route_key'                   => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                    => 'event_id',
//                 'database_bound_data_preprocessor'      => 'associationPreprocessor',
//                 'database_bound_data_postprocessor'     => 'associationPostprocessor',
//                 'moderate_route'                         => 'events/event/moderate',
//                 'moderate_route_entity_key'             => 'event_id',
//                 'suggest_form'                           => 'Project\Form\SuggestEventForm',
                'many_to_one_update_columns' => [],
                'update_columns'             => [
                    'roleId'    => 'id',
                    'name'      => 'role_id',
                    'isDefault' => 'is_default',
                    'parentId'  => 'parent_id',
                    'createdOn' => 'create_datetime',
                    'createdBy' => 'create_by',
                ],
            ],
            'user-role-link' => [
                'name'                          => 'user-role-link',
                'table_name'                    => 'user_role_linker',
                'table_key'                     => 'id',
                'entity_key_field'              => 'linkId',
                'sion_model_class'              => Model\UserTable::class,
                'row_processor_function'        => 'processUserRoleLinkerRow',
                'depends_on_entities'           => ['user-role', 'user'],
                'name_field'                    => 'roleName',
                'required_columns_for_creation' => [ //required for creation
                    'userId',
                    'roleId',
                ],
                'index_route'                   => 'juser',
                'create_action_form'            => Form\CreateRoleForm::class,
                'create_action_redirect_route'  => 'juser',
                'enable_delete_action'          => true,
                'delete_action_redirect_route'  => 'juser',
                'many_to_one_update_columns'    => [],
                'update_columns'                => [
                    'linkId'    => 'id',
                    'userId'    => 'user_id',
                    'roleId'    => 'role_id',
                    'createdOn' => 'create_datetime',
                    'createdBy' => 'create_by',
                ],
            ],
        ],
    ],
];
