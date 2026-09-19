# Request

The `CHTTP_Request` class provides an object-oriented way to interact with the current HTTP request. It extends Symfony's `Request` class and adds convenient methods for retrieving input, files, cookies, and headers.

---

### Accessing The Request

Use `c::request()` or `CHTTP::request()` to get the current request instance:

```php
<?php
class Controller_User extends CController {
    public function store() {
        $request = c::request();
        $name = $request->input('name');

        // ...
    }
}
```

The `c::request()` helper also supports shorthand access:

```php
<?php
// Get the full request object
$request = c::request();

// Get a single input value
$name = c::request('name');

// Get a single input value with default
$name = c::request('name', 'Guest');

// Get multiple input values
$data = c::request(['name', 'email']);
```

---

### Request Path and URL

#### Path

The `path` method returns the request path. For a request to `http://example.com/foo/bar`, the path is `foo/bar`:

```php
$path = $request->path();
```

#### Pattern Matching

The `is` method checks if the request path matches a given pattern. Use `*` as a wildcard:

```php
if ($request->is('admin/*')) {
    // Request is for an admin page
}
```

#### URL

```php
// URL without query string
$url = $request->url();
// http://example.com/user/profile

// Full URL with query string
$fullUrl = $request->fullUrl();
// http://example.com/user/profile?page=2

// Append query parameters to the current URL
$url = $request->fullUrlWithQuery(['sort' => 'name']);
// http://example.com/user/profile?page=2&sort=name
```

#### Segments

Access individual URI segments by index (1-based):

```php
// URL: /app/user/profile
$request->segment(1); // 'app'
$request->segment(2); // 'user'
$request->segment(3); // 'profile'
$request->segment(4); // null

$request->segments(); // ['app', 'user', 'profile']
```

---

### Request Method

```php
$method = $request->method(); // 'GET', 'POST', etc.

if ($request->isMethod('post')) {
    // Handle POST request
}
```

---

### Request Headers

```php
// Get a header value
$contentType = $request->header('Content-Type');

// With default
$token = $request->header('X-Custom-Token', 'none');

// Check if header exists
if ($request->hasHeader('X-Api-Key')) {
    // ...
}

// Get the Bearer token from the Authorization header
$token = $request->bearerToken();
```

---

### Retrieving Input

The `input` method retrieves values from the entire request payload (query string, form data, and JSON body):

```php
// Get a single value
$name = $request->input('name');

// With default
$name = $request->input('name', 'Guest');

// Get a nested value using dot notation
$city = $request->input('address.city');

// Get all input
$all = $request->input();
```

#### Typed Input

Retrieve input as a specific type:

```php
$name = $request->string('name');           // CStringable
$active = $request->boolean('active');      // true/false
$count = $request->integer('count');        // int
$price = $request->float('price');          // float
$date = $request->date('birthday');         // Carbon instance
$items = $request->collect('items');        // CCollection
```

#### Query String Only

Retrieve only query string parameters (ignoring POST body):

```php
$page = $request->query('page', 1);
$allQuery = $request->query();
```

#### POST Body Only

Retrieve only POST body parameters:

```php
$email = $request->post('email');
```

#### All Input

```php
// Get all input data
$all = $request->all();

// Get only specific keys
$data = $request->only(['name', 'email']);

// Get all except specific keys
$data = $request->except(['password', '_token']);
```

---

### Checking Input Presence

```php
// Check if a key exists (even if empty)
if ($request->has('name')) { }

// Check if any of the keys exist
if ($request->hasAny(['name', 'email'])) { }

// Check if a key exists and is not empty
if ($request->filled('name')) { }

// Check if a key is missing or empty
if ($request->isNotFilled('name')) { }

// Check if a key is missing entirely
if ($request->missing('name')) { }
```

#### Conditional Callbacks

Execute a callback only when a key is present or filled:

```php
$request->whenHas('name', function ($name) {
    // 'name' is present
});

$request->whenFilled('name', function ($name) {
    // 'name' is present and not empty
}, function () {
    // 'name' is missing or empty (optional default)
});
```

---

### JSON Input

For requests with `Content-Type: application/json`, use the `json` method:

```php
$data = $request->json();           // ParameterBag
$name = $request->json('name');     // single value
```

The `input` method also works with JSON payloads automatically.

---

### Files

```php
// Get an uploaded file
$file = $request->file('photo');

// Check if a file was uploaded
if ($request->hasFile('photo')) {
    $file = $request->file('photo');

    // Get original filename
    $name = $file->getClientOriginalName();

    // Get file extension
    $ext = $file->getClientOriginalExtension();

    // Get MIME type
    $mime = $file->getMimeType();

    // Get file size in bytes
    $size = $file->getSize();

    // Move to a destination
    $file->move('/path/to/uploads', 'newname.jpg');
}
```

---

### Cookies

```php
// Get a cookie value
$theme = $request->cookie('theme', 'light');

// Check if a cookie exists
if ($request->hasCookie('session_id')) {
    // ...
}
```

---

### Content Type Detection

```php
// Check if request expects JSON response
if ($request->wantsJson()) { }

// Check if request accepts JSON
if ($request->acceptsJson()) { }

// Check if request accepts HTML
if ($request->acceptsHtml()) { }

// Check if request content type is JSON
if ($request->isJson()) { }

// Check if request is AJAX (XMLHttpRequest)
if ($request->ajax()) { }

// Check if request is secure (HTTPS)
if ($request->secure()) { }
```

---

### Client Information

```php
// Client IP address
$ip = $request->ip();

// All client IPs (including proxies)
$ips = $request->ips();

// User agent string
$ua = $request->userAgent();

// Browser detection
$browser = $request->browser();
```

---

### Validation

Validate the request input directly:

```php
<?php
class Controller_User extends CController {
    public function store() {
        $validated = c::request()->validate([
            'name' => 'required|max:255',
            'email' => 'required|email',
            'age' => 'nullable|integer|min:0',
        ]);

        // $validated contains only the validated data
        UserModel::create($validated);
    }
}
```

`validated()` only returns attributes that have a rule — with one exception: an
attribute validated as `array` is returned **whole**, including nested keys no rule
looked at. So with `['address' => 'array', 'address.city' => 'required']`, a client
can send `address[is_admin]=1` and it comes back inside `$validated`. That default is
deprecated; opt in to dropping such keys once, for every validator, in the app's
`bootstrap.php`:

```php
<?php
c::validator()->excludeUnvalidatedArrayKeys();
```

or per validator with `$validator->excludeUnvalidatedArrayKeys()`. With it on, the
example above returns only `address.city`. Apps that rely on the whole array coming
through can keep the current behaviour explicitly with
`c::validator()->includeUnvalidatedArrayKeys()`; the default is expected to flip in
the next major version.

---

### Flash Data

Flash input data to the session for the next request (useful after validation errors):

```php
// Flash all input
$request->flash();

// Flash only specific keys
$request->flashOnly(['name', 'email']);

// Flash all except sensitive data
$request->flashExcept(['password']);

// Retrieve old input (in the next request)
$name = $request->old('name');
```

---

### Debugging

Dump the request input for debugging:

```php
// Dump specific keys and continue
$request->dump('name', 'email');

// Dump specific keys and die
$request->dd('name', 'email');
```
