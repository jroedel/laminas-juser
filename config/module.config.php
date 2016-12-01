<?php
namespace JUser;
return [
    'zfcuser' => [
        'zend_db_adapter' => 'Zend\Db\Adapter\Adapter',
        // telling ZfcUser to use our own class
        'user_entity_class'       => 'JUser\Model\User',
        
        'auth_adapters' => [ 
            100 => 'ZfcUser\Authentication\Adapter\Db',
            50 => 'GoalioRememberMe\Authentication\Adapter\Cookie'
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
        'unauthorized_strategy' => 'JUser\View\RedirectionStrategy',
        
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
        'identity_provider' => 'BjyAuthorize\Provider\Identity\ZfcUserZendDb',

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
            'BjyAuthorize\Provider\Role\ZendDb' => [
                'table'             => 'user_role',
                'role_id_field'     => 'role_id',
                'parent_role_field' => 'parent',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'juser' => [
                'type'    => 'Literal',
                'options' => [
                    'route'    => '/users',
                    'defaults' => [
                        'controller' => 'JUser\Controller\Users',
                        'action'     => 'index',
                    ],
                ],
                'may_terminate' => true,
                'child_routes' => [
                    'user' => [
                        'type'    => 'Segment',
                        'options' => [
                            'route'    => '/:user_id',
                            'constraints' => [
                                'user_id' => '[0-9]{1,5}',
                            ],
                            'defaults' => [
                                'controller' => 'JUser\Controller\Users',
                            ],
                        ],
                        'may_terminate' => false,
                        'child_routes' => [
                            'edit' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/edit',
                                    'defaults' => [
                                        'action'     => 'edit',
                                    ],
                                ],
                            ],
                            'delete' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/delete',
                                    'defaults' => [
                                        'action'     => 'delete',
                                    ],
                                ],
                            ],
                            'change-password' => [
                                'type'    => 'Literal',
                                'options' => [
                                    'route'    => '/change-password',
                                    'defaults' => [
                                        'action'     => 'change-password',
                                    ],
                                ],
                            ],
                            'show' => [
                                'type'    => 'Literal',
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
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/create',
                            'defaults' => [
                                'controller' => 'JUser\Controller\Users',
                                'action'     => 'create',
                            ],
                        ],
                    ],
                    'create-role' => [
                        'type'    => 'Literal',
                        'options' => [
                            'route'    => '/roles/create',
                            'defaults' => [
                                'controller' => 'JUser\Controller\Users',
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
            'JUser\Controller\Users' => 'JUser\Service\UsersControllerFactory',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'users' => __DIR__ . '/../view',
        ],
    ],
    'service_manager' => [
        'factories' => [
            'JUserMailMessage'            => 'JUser\Service\MailMessageFactory',
            'JUserMailTransport'          => 'JUser\Service\MailTransportFactory',
            'JUser\Model\UserTable'       => 'JUser\Service\UserTableFactory',
            'JUser\Form\EditUserForm'     => 'JUser\Service\EditUserFormFactory',
            'JUser\Form\CreateRoleForm'   => 'JUser\Service\CreateRoleFormFactory',
        ],
        'invokables'  => [
            'JUser\View\RedirectionStrategy' => 'JUser\View\RedirectionStrategy',
        ],
    ],
];
