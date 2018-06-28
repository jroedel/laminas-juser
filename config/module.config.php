<?php
namespace JUser;

use JUser\Model\User;
use Zend\Db\Adapter\Adapter;
use ZfcUser\Authentication\Adapter\Db;
use GoalioRememberMe\Authentication\Adapter\Cookie;
use JUser\View\RedirectionStrategy;
use BjyAuthorize\Provider\Identity\ZfcUserZendDb;
use BjyAuthorize\Provider\Role\ZendDb;
use Zend\Router\Http\Literal;
use Zend\Router\Http\Segment;
use JUser\Service\UsersControllerFactory;
use JUser\Controller\UsersController;
use JUser\View\Helper\IpPlace;
use JUser\View\Helper\UserWithIp;
use JUser\Service\UserTableFactory;
use JUser\Model\UserTable;
use JUser\Form\EditUserForm;
use JUser\Service\EditUserFormFactory;
use JUser\Service\CreateRoleFormFactory;
use JUser\Form\CreateRoleForm;
use JUser\Service\ConfigServiceFactory;
use Zend\ServiceManager\Proxy\LazyServiceFactory;
use Zend\Session;
use Zend\Session\Storage\SessionArrayStorage;
use Zend\Session\Validator\RemoteAddr;
use Zend\Session\Validator\HttpUserAgent;

return [
    'zfcuser' => [
        'zend_db_adapter' => Adapter::class,
        // telling ZfcUser to use our own class
        'user_entity_class'       => User::class,

        'auth_adapters' => [
            100 => Db::class,
        ],

        'enable_default_entities' => false,

        'enable_registration' => false,

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

    'bjyauthorize' => [
        'unauthorized_strategy' => RedirectionStrategy::class,

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
        'identity_provider' => ZfcUserZendDb::class,

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

            // this will load roles from the user_role table in a database
            // format: user_role(role_id(varchar], parent(varchar]]
            ZendDb::class => [
                'table'             => 'user_role',
                'role_id_field'     => 'role_id',
                'identifier_field_name' => 'id',
                'parent_role_field' => 'parent',
            ],
            
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
            'juser' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/users',
                    'defaults' => [
                        'controller' => UsersController::class,
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
                            'defaults' => [
                                'controller' => UsersController::class,
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
                                'controller' => UsersController::class,
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'create-role' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/roles/create',
                            'defaults' => [
                                'controller' => UsersController::class,
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
            UsersController::class => UsersControllerFactory::class,
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
            'ipPlace'		        => IpPlace::class,
            'userWithIp'		    => UserWithIp::class,
       	],
    ],
    'service_manager' => [
        'factories' => [
            UserTable::class        => UserTableFactory::class,
            EditUserForm::class     => EditUserFormFactory::class,
            CreateRoleForm::class   => CreateRoleFormFactory::class,
            'JUser\Config'          => ConfigServiceFactory::class,
            // Configures the default SessionManager instance
            Session\ManagerInterface::class => Session\Service\SessionManagerFactory::class,
            // Provides session configuration to SessionManagerFactory
            Session\Config\ConfigInterface::class => Session\Service\SessionConfigFactory::class,
        ],
        'invokables'  => [
            RedirectionStrategy::class => RedirectionStrategy::class,
        ],
        'lazy_services' => [
            // Mapping services to their class names is required
            // since the ServiceManager is not a declarative DIC.
            'class_map' => [
                CreateRoleForm::class => CreateRoleForm::class,
                EditUserForm::class => EditUserForm::class,
            ],
        ],
        'delegators' => [
            CreateRoleForm::class => [
                LazyServiceFactory::class,
            ],
            EditUserForm::class => [
                LazyServiceFactory::class,
            ],
        ],
        'aliases' => [
            \Zend\Session\SessionManager::class => Session\ManagerInterface::class,
        ],
        
    ],
    'session_storage' => [
        'type' => SessionArrayStorage::class
    ],
    'session_config' => [
        // Set the session and cookie expiries to 30 days
        'cache_expire' => 30*24*60*60,
        'cookie_lifetime' => 30*24*60*60,
        'gc_maxlifetime'      => 30*24*60*60
    ],
];
