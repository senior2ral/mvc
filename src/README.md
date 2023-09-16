# PMF
PHP MVC Framework

## Installation

```
composer require senior2ral/pmf
```

## Structure

This a multi-module [MVC][mvc-pattern] structure.

```
app/
├── config
│   └── config.php
│
├── lib
│   └── Auth.php
│
├── middlewares
│   └── Api.php
│
├── models
│   ├── Products.php
│   └── Users.php
│
└── sites
    └── api
        ├── config
        │   ├── modules.php
        │   ├── routes.php
        │   └── config.php
        │   
        ├── modules
        │   ├── auth
        │   │   ├── controllers
        │   │   │   ├── LoginController.php
        │   │   │   └── RegisterController.php
        │   │   └── Module.php
        │   │   
        │   └── products
        │       ├── controllers
        │       │   ├── CreateController.php
        │       │   ├── ListController.php
        │       │   └── DeleteController.php
        │       └── Module.php
        │      
        └── index.php
```

## Team

- <a href="https://github.com/senior2ral">Tural Ilyasov</a><br/>