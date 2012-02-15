<?php
if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50300) {
  error(500, 'dispatch requires at least PHP 5.3 to run.');
}

// throw this when pass() is called
class PassException extends Exception {}

// for failed preconditions
class PreconditionException extends Exception {}

function config($key, $value = null) {

	static $_config = null;

	if (!defined('CONFIG_PATH')) {
		define('CONFIG_PATH', __DIR__.'/config.ini');
	}

	// try to load a config.ini file
	if ($_config == null) {
		$_config = array();
		if (file_exists(CONFIG_PATH)) {
			$_config = parse_ini_file(CONFIG_PATH, true);
		}
	}

	if ($value == null) {
		return (isset($_config[$key]) ? $_config[$key] : null);
	}

	$_config[$key] = $value;
}

function to_b64($str) {
  $str = base64_encode($str);
  $str = preg_replace('/\//', '_', $str);
  $str = preg_replace('/\+/', '.', $str);
  $str = preg_replace('/\=/', '-', $str);
  return trim($str, '-');
}

function from_b64($str) {
  $str = preg_replace('/\_/', '/', $str);
  $str = preg_replace('/\./', '+', $str);
  $str = preg_replace('/\-/', '=', $str);
  $str = base64_decode($str);
  return $str;
}

// having mcrypt will let you use encrypt(), decrypt() and cookie stuff
if (extension_loaded('mcrypt')) {

	function encrypt($decoded) {
		if (($secret = config('secret')) == null) {
			error(500, 'encrypt() requires that you define [secret] through config() or in your config.ini');
		}
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv_code = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		return to_b64(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $secret, $decoded, MCRYPT_MODE_ECB, $iv_code));
	}

	function decrypt($encoded) {
		if (($secret = config('secret')) == null) {
			error(500, 'decrypt() requires that you define [secret] through config() or in your config.ini');
		}
		$enc_str = from_b64($encoded);
		$iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
		$iv_code = mcrypt_create_iv($iv_size, MCRYPT_RAND);
		$enc_str = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $secret, $enc_str, MCRYPT_MODE_ECB, $iv_code);
		return rtrim($enc_str, "\0");
	}

	function set_cookie($name, $value, $span = 604800) {
		if (($secret = config('secret')) == null) {
			error(500, 'set_cookie() requires that you define [secret] through config() or in your config.ini');
		}
		$stamp  = time() + $span;
		$cksum  = md5("{$value}{$stamp}");
		$token  = encrypt("{$value}-{$stamp}-{$cksum}");
		setcookie($name, $token, time() + 314496000, '/'); // 10 years
	}

	function get_cookie($name) {
		if (($secret = config('secret')) == null) {
			error(500, 'get_cookie() requires that you define [secret] through config() or in your config.ini');
		}
		if (!isset($_COOKIE[$name])) {
			return null;
		}
		$token = decrypt($_COOKIE[$name]);
		list($value, $stamp, $cksum) = explode('-', $token);
		if (md5("{$value}{$stamp}") === $cksum && time() < $stamp) {
			return $value;
		}
		return null;
	}

	function delete_cookie() {
		$cookies = func_get_args();
		foreach ($cookies as $ck) {
			setcookie($ck, '', -10, '/');
		}
	}

}

function html($str, $enc = 'UTF-8', $flags = ENT_QUOTES) {
  return htmlentities($str, $flags, $enc);
}

function from($source, $name) {
  if (is_array($name)) {
    $data = array();
    foreach ($name as $k) {
      $data[$k] = isset($source[$k]) ? $source[$k] : null ;
    }
    return $data;
  }
  return isset($source[$name]) ? $source[$name] : null ;
}

function error($code = 500, $message = "Internal server error") {
	@header("HTTP/1.0 {$code} {$message}", true, $code);
	die($message);
}

function stash($name, $value = null) {

	static $_stash = array();

	if ($value === null) {
    return isset($_stash[$name]) ? $_stash[$name] : null;
  }

	$_stash[$name] = $value;

	return $value;
}

function method($verb = null) {
  if ($verb == null || (strtoupper($verb) == strtoupper($_SERVER['REQUEST_METHOD']))) {
    return strtoupper($_SERVER['REQUEST_METHOD']);
  }
	error(400, 'Bad request');
}

function client_ip() {
  if (isset($_SERVER['HTTP_CLIENT_IP'])) {
    return $_SERVER['HTTP_CLIENT_IP'];
  } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    return $_SERVER['HTTP_X_FORWARDED_FOR'];
  }
  return $_SERVER['REMOTE_ADDR'];
}

function redirect($uri, $code = 302) {
  header('Location: '.$uri, true, $code);
  exit;
}

function redirect_if($expr, $uri, $code = 302) {
	!!$expr && redirect($uri, $code);
}

function partial($view, $locals = null) {

  if (is_array($locals) && count($locals)) {
    extract($locals, EXTR_SKIP);
  }

	$view_root = config('views');
	$view_root = ($view_root == null) ? __DIR__.'/views' : $view_root;

  $path = basename($view);
  $view = preg_replace('/'.$path.'$/', "_{$path}", $view);
  $view = "{$view_root}/{$view}.html.php";

  if (file_exists($view)) {
    ob_start();
    require $view;
    return ob_get_clean();
  } else {
		error(500, "Partial [{$view}] not found");
	}

  return '';
}

function content($value = null) {
  return stash('__content__', $value);
}

function render($view, $locals = null, $layout = null) {

  if (is_array($locals) && count($locals)) {
    extract($locals, EXTR_SKIP);
  }

	$view_root = config('views');
	$view_root = ($view_root == null) ? __DIR__.'/views' : $view_root;

  ob_start();
  include "{$view_root}/{$view}.html.php";
  content(trim(ob_get_clean()));

  if ($layout !== false) {

		if ($layout == null) {
			$layout = config('layout');
			$layout = ($layout == null) ? 'layout' : $layout;
		}

		$layout = "{$view_root}/{$layout}.html.php";

    ob_start();
    require $layout;
    echo trim(ob_get_clean());

  } else {
    echo content();
  }
}

function precondition() {

	static $cb_map = array();

	$args = func_get_args();
	if (count($args) < 1) {
		error(500, 'Call to precondition() requires at least 1 argument');
	}

	$name = array_shift($args);
	if (count($args) && is_callable($args[0])) {
		$cb_map[$name] = $args[0];
	} else {
		if (isset($cb_map[$name]) && is_callable($cb_map[$name])) {
			if (!call_user_func_array($cb_map[$name], $args)) {
				throw new PreconditionException('Precondition not met');
			}
		}
	}
}

function preload($sym, $cb = null) {

	static $cb_map = array();

	if (is_array($sym) && count($sym) > 0) {
		foreach ($cb_map as $sym => $cb) {
			call_user_func($cb, $sym);
		}
		return;
	}

	if (!is_string($sym) || !is_callable($cb)) {
		error(500, 'Call to preload() requires either a symbol + callback or a list of symbols to preload');
	}

	$cb_map[$sym] = $cb;
}

function route_to_regex($route) {
  $route = preg_replace_callback('@:[\w]+@i', function ($matches) {
    $token = str_replace(':', '', $matches[0]);
    return '(?P<'.$token.'>[a-z0-9_\0-\.]+)';
  }, $route);
  return '@^'.rtrim($route, '/').'$@i';
}

function route($method, $pattern, $callback = null) {

  // callback map by request type
  static $route_map = array(
    'GET' => array(),
    'POST' => array(),
    'PUT' => array(),
    'DELETE' => array()
  );

  $method = strtoupper($method);

  if (in_array($method, array('GET', 'POST', 'PUT', 'DELETE'))) {

    // a callback was passed, so we create a route defiition
    if ($callback !== null) {

      // create a route entry for this pattern
      $route_map[$method][$pattern] = array(
        'expression' => route_to_regex($pattern),
        'callback' => $callback
      );

    } else {

      // callback is null, so this is a route invokation. look up the callback.
      foreach ($route_map[$method] as $pat => $obj) {

        // if the requested uri ($pat) has a matching route, let's invoke the cb
        if (preg_match($obj['expression'], $pattern, $vals)) {

          // construct the params for the callback
          array_shift($vals);
          preg_match_all('@:([\w]+)@', $pat, $keys, PREG_PATTERN_ORDER);
          $keys = array_shift($keys);
          $params = array();

          foreach ($keys as $index => $id) {
            $id = substr($id, 1);
            if (isset($vals[$id])) {
              array_push($params, urlencode($vals[$id]));
            }
          }

					// call preloaders if we have symbols
					if (count($keys)) {
						preload(array_values($keys));
					}

					// if no call to pass was made, exit after first route
					try {
						if (is_callable($obj['callback'])) {
							call_user_func_array($obj['callback'], $params);
						}
						break;
					} catch (PreconditionException $e) {
						error(403, 'Precondition not met');
						break;
					} catch (PassException $e) {
						continue;
					}

        }
      }
    }
  } else {
    error("Request method [{$method}] is not supported.");
  }
}

function get($path, $cb) {
	route('GET', $path, $cb);
}

function post($path, $cb) {
	route('POST', $path, $cb);
}

function pass() {
	throw new PassException('Jumping to next handler');
}

function dispatch($fake_uri = null) {

  // extract the request params from the URI (/controller/etc/etc...)
  $parts = preg_split('/\?/', ($fake_uri == null ? $_SERVER['REQUEST_URI'] : $fake_uri), -1, PREG_SPLIT_NO_EMPTY);

  $uri = trim($parts[0], '/');
  $uri = strlen($uri) ? $uri : 'index';

  // and route the URI through
  route(method(), "/{$uri}");
}
?>
