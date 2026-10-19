# Controller

Controllers handle incoming HTTP requests and return responses. All controllers must extend `CController` and are stored in the `controllers/` directory.

---

### Basic Controller

A minimal controller with a single action:

```php
<?php
class Controller_Example extends CController {
    public function index() {
        return c::response('Hello, World!');
    }
}
```

This controller handles requests to `/example` and `/example/index`.

See [Routing](/docs/basic/routing) for details on how URLs map to controllers and methods.

---

### File Naming

Controller classes follow the underscore-to-directory naming convention. The `Controller_` prefix maps to the `controllers/` directory, and each subsequent segment maps to a subdirectory or filename (lowercase).

| Class Name | File Path |
|---|---|
| `Controller_Home` | `controllers/home.php` |
| `Controller_App_Home` | `controllers/app/home.php` |
| `Controller_Admin_Setting_Plan` | `controllers/admin/setting/plan.php` |

---

### Method Visibility

Only `public` methods are accessible via URL. Use `protected` or `private` for internal helper methods that should not be routable.

```php
<?php
class Controller_Example extends CController {
    public function index() {
        // Accessible via /example/index ✓
        $data = $this->loadData();

        return c::response($data);
    }

    protected function loadData() {
        // NOT accessible via URL ✗ (returns 404)
        return 'internal data';
    }
}
```

Magic methods (`__construct`, `__call`, etc.) and internal controller methods (`callAction`, `middleware`, `getMiddleware`) are also blocked from URL access.

---

### Returning Responses

Controllers can return different types of responses:

#### CApp (Page Builder)

The most common pattern in CF. `CApp` is a page builder object that renders a full HTML page with the configured theme, navigation, and assets.

```php
<?php
class Controller_App_Dashboard extends CController {
    public function index() {
        $app = c::app();
        $app->title('Dashboard');
        $app->setView('dashboard');

        return $app;
    }
}
```

You can also build the page programmatically by adding elements:

```php
<?php
class Controller_App_User extends CController {
    public function index() {
        $app = c::app();
        $app->title('Users');

        $table = $app->addTable();
        $table->setDataFromModel(UserModel::class);
        $table->addColumn('name')->setLabel('Name');
        $table->addColumn('email')->setLabel('Email');

        return $app;
    }
}
```

> **Note:** Using `echo $app->render()` is deprecated (reported by the deprecation collector since 1.9). Always use `return $app`.
> When you need the page as an HTML string instead (a PDF, an email body), use `$app->toHtml()`:
>
> ```php
> $dompdf->loadHtml($app->toHtml());
> ```

#### Plain Response

Return a simple text or HTML response:

```php
<?php
public function health() {
    return c::response('OK');
}

public function page() {
    return c::response('<h1>Hello</h1>', 200, ['Content-Type' => 'text/html']);
}
```

#### JSON Response

Return JSON data for API endpoints:

```php
<?php
public function apiUsers() {
    $users = UserModel::all();

    return c::response()->json([
        'status' => 'success',
        'data' => $users->toArray(),
    ]);
}
```

#### View Response

Return a rendered Blade or PHP view:

```php
<?php
public function about() {
    return c::view('about', [
        'title' => 'About Us',
        'team' => TeamModel::all(),
    ]);
}
```

#### Redirect

Redirect to another URL or controller:

```php
<?php
public function index() {
    return c::redirect('app/home');
}

public function store() {
    // ... process form
    return c::redirect()->back();
}
```

#### Abort

Return an error response:

```php
<?php
public function show($id) {
    $item = ItemModel::find($id);
    if (!$item) {
        c::abort(404);
    }

    // ...
}
```

---

### Authentication

By default, when `auth.enable` is `true` in `config/app.php`, all pages require login and will redirect unauthenticated users to the login page. To make a page publicly accessible, disable authentication using `setLoginRequired(false)`:

```php
<?php
class Controller_Home extends CController {
    public function index() {
        $app = c::app();
        $app->setLoginRequired(false);
        $app->addView('landing');

        return $app;
    }
}
```

Available methods on `CApp`:

| Method | Description |
|---|---|
| `setLoginRequired(false)` | Disable auth check for the current request |
| `setLoginRequired(true)` | Enable auth check (default when `auth.enable` is `true`) |
| `disableAuth()` | Alias for `setLoginRequired(false)` |
| `enableAuth()` | Alias for `setLoginRequired(true)` |
| `isLoginRequired()` | Check if auth is currently required |

For base controllers that handle multiple public pages, set it in the constructor:

```php
<?php
class MyApp_Controller_PublicController extends CController {
    public function __construct() {
        parent::__construct();

        c::app()->setLoginRequired(false);
    }
}
```

---

### Middleware

Register middleware in the controller constructor. Middleware runs before the controller action is executed.

```php
<?php
class Controller_Api_Data extends CController {
    public function __construct() {
        parent::__construct();

        $this->middleware(MyApp_Middleware_AuthApi::class);
    }

    public function index() {
        // Only accessible if middleware passes
    }
}
```

#### Restricting Middleware to Specific Methods

Use `only()` or `except()` to limit which methods the middleware applies to:

```php
<?php
class Controller_Admin extends CController {
    public function __construct() {
        parent::__construct();

        // Only apply to 'store' and 'update' methods
        $this->middleware(MyApp_Middleware_VerifyCsrf::class)
            ->only(['store', 'update']);

        // Apply to all methods except 'index'
        $this->middleware(MyApp_Middleware_RequireAdmin::class)
            ->except('index');
    }
}
```

---

### Constructor

Use the constructor for setup that applies to all methods in the controller, such as registering middleware or configuring the application instance.

Always call `parent::__construct()` first:

```php
<?php
class Controller_Docs extends CController {
    public function __construct() {
        parent::__construct();

        c::app()->setLoginRequired(false);
        c::app()->setTheme('docs-theme');
    }

    public function index() {
        // Theme and login settings are already applied
    }
}
```

---

### Base Controllers

For shared behavior across multiple controllers, create a base controller class in your application's `libraries/` directory:

```php
<?php
// libraries/MyApp/Controller/AppController.php
class MyApp_Controller_AppController extends CController {
    public function __construct() {
        parent::__construct();

        c::app()->setLoginRequired(true);
        c::manager()->theme()->setTheme('my-theme');
    }
}
```

Then extend it in your controllers:

```php
<?php
class Controller_App_Home extends MyApp_Controller_AppController {
    public function index() {
        $app = c::app();
        $app->title('Home');
        // Theme and login are already configured by the base class

        return $app;
    }
}
```

---

### Registering Assets

Register CSS and JavaScript assets from within a controller:

```php
<?php
public function index() {
    $app = c::app();

    CManager::registerCss('my-app/dashboard.css');
    CManager::registerJs('my-app/dashboard.js');

    // Or using the shorthand
    c::manager()->registerCss('build/main.css');
    c::manager()->registerJs('build/main.js');

    return $app;
}
```

---

### Controller URL Helper

Get the URL that maps to a controller class:

```php
<?php
class Controller_App_User extends CController {
    public function index() {
        // Get the base URL for this controller
        $url = static::controllerUrl();
        // Returns: http://example.com/app/user/
    }
}
```

---

### HTTP Verb Prefixes

Restrict a controller method to specific HTTP verbs by prefixing the method name. See [Routing - HTTP Verb Prefixes](/docs/basic/routing) for full details.

```php
<?php
class Controller_Api_Product extends CController {
    public function getList() {
        // GET /api/product/list
    }

    public function postCreate() {
        // POST /api/product/create
    }

    public function deleteRemove($id) {
        // DELETE /api/product/remove/5
    }
}
```
