# ✨ blink

A zero-dependency PHP web framework. One folder in your project, no composer install, no build step.

```php
// index.php
require_once "core/setup.php";
```

```php
// routes/web.php
use Blink\Http\Router;

Router::GET("/", fn () => "hello");

Router::GET("/user/{id}", function (int $id) {
	return App\Model\User::getByID($id, Blink\Model::MUST_EXIST);
});
```

That is a working site. Blink creates its own `data/` directory, applies its schema on the first request, and writes a `.gitignore` if the project has none.

Requires PHP 8.1 or newer. MySQL or SQLite.


## 📃 what's in it

| | |
|----|----|
| **Router** | Routes as closures, callables or controller attributes, with typed path parameters |
| **Query engine** | A fluent builder: joins, groups, aggregates, subqueries, search, one API for MySQL and SQLite |
| **Model** | An ORM with lazy loading, field conversion, lifecycle hooks and transactional deletes |
| **Essence** | A dependency container behind every handler, command and middleware |
| **Ether** | Middleware around the router, queries, events and the error page |
| **Lightrail** | An event bus with immediate, shutdown, background and batched handlers |
| **Ticker** | Background tasks and cron-style scheduling, one cron line and a runner |
| **Terra** | Schema as migrations, compiled for both engines from one description |
| **Trace** | An audit log that records every model change on its own |
| **Console** | `php core/blink <command>`, and a short way to write your own |
| **Error page** | Stack traces with source, request context, metrics, and advice on what to fix |

Plus caching, sessions, access tokens, uploads, templating and localization.


## 🤓 documentation

Everything is on the **[wiki](https://github.com/Belikhun/blink/wiki)**.

Start with [Home](https://github.com/Belikhun/blink/wiki) for a quick setup, then [Modules](https://github.com/Belikhun/blink/wiki/Modules) for how an application wires itself in.


## 🚀 in production

```
* * * * * cd /var/www/site && php core/blink ticker:tick >> /dev/null 2>&1
```

```
php core/blink ticker:run
php core/blink ticker:run lightrail
```

The cron line fires recurring work once a minute; the runners drain the task queues. Both are optional until you use them. Turn `CONFIG::$DEBUG` off and error reports keep capturing failures without showing a stranger anything.

---

<sup><code>✨ blink by Belikhun</code></sup>
