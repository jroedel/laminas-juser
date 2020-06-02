<?php

namespace JUser;

use Zend\Db\Adapter\Adapter;
use ZfcUser\Authentication\Adapter\Db;
use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use Zend\ServiceManager\Proxy\LazyServiceFactory;
use Zend\Session;
use Zend\Session\Storage\SessionArrayStorage;
use JUser\Provider\Identity\ZfcUserZendDbPlusSelfAsRole;
use JUser\Service\ZfcUserZendDbPlusSelfAsRoleFactory;
use JUser\Provider\Role\UserIdRoles;
use JUser\Service\UserIdRolesFactory;

return [
    'zfcuser' => [
        'zend_db_adapter' => Adapter::class,
        // telling ZfcUser to use our own class
        'user_entity_class'       => Model\User::class,

        'auth_adapters' => [
            100 => Db::class,
        ],

        'enable_default_entities' => false,

        'enable_registration' => true,

        'use_registration_form_captcha' => true,

        'form_captcha_options' => [
                'class'   => 'figlet',
                'options' => [
                        'wordLen'    => 5,
                        'expiration' => 300,
                        'timeout'    => 300,
                ],
        ],
        'enable_display_name' => true,

        'enable_username' => true,

        'auth_identity_fields' => [ 'username', 'email' ],

        'login_redirect_route' => 'home',

        'logout_redirect_route' => 'home',

        'use_redirect_parameter_if_present' => true,

        'enable_user_state' => true,
        //the user state will stay at 0 until the user has been validated
        'default_user_state' => 0,

        'allowed_login_states' => [1],

        'user_login_widget_view_template' => 'zfc-user/user/login',
    ],
    
    'juser' => [
        'verification_email_message' => [
            'sender' => 'juser@example.com',
            'subject' => 'Sign in verification',
            'body' => 'Thanks for signing up!, Please enter the following code into the app where you are '
            .'signing in:%s'.PHP_EOL.'If you did not request a login, please ignore this message. Thanks!',
        ],
        'api_verification_token_length' => 6,
        'api_verification_token_expiration_interval' => 'P1D',
    ],
    'bjyauthorize' => [
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
         * Zend\Db adapter.
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
            \BjyAuthorize\Provider\Role\ZendDb::class => [
                'table'                 => 'user_role',
                'identifier_field_name' => 'id',
                'role_id_field'         => 'role_id',
                'parent_role_field'     => 'parent_id',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'api-v1-login' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/api/v1/users/login',
                    'defaults' => [
                        'action' => 'login',
                        'controller' => Controller\LoginV1ApiController::class,
                    ],
                ],
            ],
            'api-v1-login-with-verification-token' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/api/v1/users/login-with-verification-token',
                    'defaults' => [
                        'action' => 'loginWithVerificationToken',
                        'controller' => Controller\LoginV1ApiController::class,
                    ],
                ],
            ],
            'api-v1-request-verification-token' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/api/v1/users/request-verification-token',
                    'defaults' => [
                        'action' => 'requestVerificationToken',
                        'controller' => Controller\LoginV1ApiController::class,
                    ],
                ],
            ],
            'juser' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/users',
                    'defaults' => [
                        'controller' => Controller\UsersController::class,
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'verify-email' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/verify-email',
                            'defaults' => [
                                'action'     => 'verifyEmail',
                            ],
                        ],
                    ],
                    'thanks' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/thanks',
                            'defaults' => [
                                'action'     => 'thanks',
                            ],
                        ],
                    ],
                    'user' => [
                        'type'    => Segment::class,
                        'options' => [
                            'route'    => '/:user_id',
                            'constraints' => [
                                'user_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'controller' => Controller\UsersController::class,
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
                            'change-password' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/change-password',
                                    'defaults' => [
                                        'action'     => 'change-password',
                                    ],
                                ],
                            ],
                            'show' => [
                                'type'    => Literal::class,
                                'options' => [
                                    'route'    => '/show',
                                    'defaults' => [
                                        'action'     => 'show',
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
                                'controller' => Controller\UsersController::class,
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'create-role' => [
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
    'controllers' => [
        'factories' => [
            Controller\UsersController::class => Service\UsersControllerFactory::class,
            Controller\LoginV1ApiController::class => Service\LoginV1ApiControllerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_map' => include __DIR__ . '/template_map.config.php',
        'template_path_stack' => [
            'users' => __DIR__ . '/../view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'ipPlace'               => View\Helper\IpPlace::class,
            'userWithIp'            => View\Helper\UserWithIp::class,
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
            \Swift_Mailer::class            => Service\SwiftMailerFactory::class,
            ZfcUserZendDbPlusSelfAsRole::class => ZfcUserZendDbPlusSelfAsRoleFactory::class,
            UserIdRoles::class              => UserIdRolesFactory::class,
            Authentication\Adapter\CredentialOrTokenQueryParams::class =>
                Service\CredentialOrTokenQueryParamsFactory::class,
            //use this to override zfcuser's register form
//             'zfcuser_register_form' => RegisterForm::class,
        ],
        'invokables'  => [
            View\RedirectionStrategy::class => View\RedirectionStrategy::class,
        ],
        'lazy_services' => [
            // Mapping services to their class names is required
            // since the ServiceManager is not a declarative DIC.
            'class_map' => [
                Form\CreateRoleForm::class   => Form\CreateRoleForm::class,
                Form\EditUserForm::class     => Form\EditUserForm::class,
                Service\Mailer::class        => Service\Mailer::class,
            ],
        ],
        'delegators' => [
            Form\CreateRoleForm::class => [
                LazyServiceFactory::class,
            ],
            Form\EditUserForm::class => [
                LazyServiceFactory::class,
            ],
            Service\Mailer::class => [
                LazyServiceFactory::class,
            ],
        ],
        'aliases' => [
            \Zend\Session\SessionManager::class => Session\ManagerInterface::class,
            'zfcuser_user_mapper'           => Model\UserTable::class,
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
                'sion_controllers'                      => [Controller\UsersController::class],
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
                    'password',
                ],
                'index_route'                           => 'juser',
//                 'index_template'                        => 'project/events/index',
                'default_route_key'                     => 'user_id',
                'show_route'                            => 'juser/user',
                'show_route_key'                        => 'user_id',
                'show_route_key_field'                  => 'userId',
                'edit_action_form'                      => Form\EditUserForm::class,
//                 'edit_action_template'                   => 'project/events/edit',
                'edit_route'                            => 'association-edit',
                'edit_route_key'                        => 'user_id',
                'edit_route_key_field'                  => 'userId',
                'create_action_form'                    => Form\EditUserForm::class,
//                 'create_action_valid_data_handler'      => 'createAssociation',
                'create_action_redirect_route'          => 'association',
                'create_action_redirect_route_key'      => 'user_id',
                'create_action_redirect_route_key_field' => 'userId',
//                 'create_action_template'                   => 'project/events/create',
                'enable_delete_action'                  => true,
//                 'delete_action_acl_resource'             => 'event_:id',
//                 'delete_action_acl_permission'             => 'delete_event',
                'delete_action_redirect_route'          => 'juser',
//                 'touch_default_field'                   => 'eventId',
//                 'touch_field_route_key'                   => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                    => 'event_id',
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
                    'password'          => 'password',
                    'createdOn'         => 'create_datetime',
                    'createdBy'         => 'create_by',
                    'updatedOn'         => 'update_datetime',
                    'updatedBy'         => 'update_by',
                    'emailVerified'     => 'email_verified',
                    'mustChangePassword' => 'must_change_password',
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
                'sion_controllers'                      => [Controller\UsersController::class],
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
                'required_columns_for_creation'         => [ //required for creation
                    'username',
                    'email',
                    'displayName',
                    'password',
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
//                 'touch_default_field'                   => 'eventId',
//                 'touch_field_route_key'                   => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                    => 'event_id',
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
//                 'sion_controllers'                      => [Controller\UsersController::class],
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
//                 'touch_default_field'                   => 'eventId',
//                 'touch_field_route_key'                   => 'event_id',
//                 'touch_json_route'                       => 'events/event/touch',
//                 'touch_json_route_key'                    => 'event_id',
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
