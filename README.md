## Dispatch PHP Micro Framework
At the very least, `dispatch()` is a front controller for your web application. It lets you define routes in your application, organize your code into controllers and views, along with some other functions useful in creating web apps.

## Basic Usage
```php
<?php
// include the library
include 'dispatch.php';

// define your routes
get('/greet', function () {
	// render a view
	render('greet-form');
});

// post handler
post('/greet', function () {
	$name = from($_POST, 'name');
	// render a view while passing some locals
	render('greet-show', array('name' => $name));
});

// serve your site
dispatch();
?>
```

## Route Symbol Preloader
This is a port of ExpressJS' route preconditions. Preloaders let you map functions against route symbols you choose. These functions then get executed when those symbols are encountered.

```php
<?php
// preload blog entry whenever a matching route has :blog_id in it
preload('blog_id', function ($blog_id) {
	$blog = Blog::findOne($blog_id);
	// stash() lets you store stuff for later use (NOT a cache)
	stash('blog', $blog);
});

// here, we have :blog_id in the route, so our preloader gets run
get('/blogs/:blog_id', function ($blog_id) {
	// pick up what we got from the stash
	$blog = stash('blog');
	render('blogs/show', array('blog' => $blog);
});
?>
```

## Preconditions
This is taken from BreezePHP. Preconditions let you setup functions that determine if execution continues or not.

```php
<?php
// if our token is invalid, print out an error
precondition('token_valid', function ($token) {
	if ($token !== md5('s3cr3t-s4uc3'.client_ip())) {
		error('Unauthorized!', 403);
	}
});

// require a valid token when accessing a page
get('/admin/:token', function ($token) {
	precondition('token_valid', $token);
	// if the precondition goes through, we render
	render('admin');
});
?>
```

## Route Pass Through
By default, dispatch will only execute the first route handler that matches the request URI. To let the route matching continue, call `pass()`.

```php
<?php
get('/blog/:slug', function ($slug) {
	// if the blog admin is what's being requested, let it through
	if ($slug == 'admin') {
		pass();
	}
	$blog = Blog::findBySlug($slug);
	render('blogs/show', array('blog' => $blog));
});

// this is our actual route handler
get('/blog/admin', function () {
	render('admin');
});
?>
```

## LICENSE
(The MIT License)

Copyright (c) 2011 Jesus A. Domingo jesus.domingo@gmail.com

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the 'Software'), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
