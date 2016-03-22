## WebSharks™ PHP Web Server

**Warning:** PHP's built-in web server was designed to aid application development. It may also be useful for testing purposes or for application demonstrations that are run in controlled environments. It is not intended to be a full-featured web server. It should not be used on a public network. See [this article](http://php.net/manual/en/features.commandline.webserver.php) for more info.

## Installation

### Via Composer

_Requires PHP 5.4+ w/ `mbstring` extension. Known to work with recent versions of PHP 7.x also._

```json
{
    "require": {
        "websharks/php-web-server": "dev-master"
    }
}
```

### Or, via Git

```bash
$ mkdir -p ~/projects/websharks;
$ git clone https://github.com/websharks/php-web-server ~/projects/websharks/php-web-server;
```

## Running the Web Server

### Required Directory Structure

- `~/www` (absolute root base directory; passed as `-t` below)
  - `/localhost` (document root sub-folder matching host name below)
    - `index.php` _... and any other web-accessible files can live here._
  - _... for as many host names as you like._

```bash
$ mkdir -p ~/www/localhost;
$ echo 'It works!' > ~/www/localhost/index.php;
```

### Start Server from a Terminal Window

```bash
$ php \
  -S localhost:8080 \
  -t ~/www \
  -d variables_order=EGPCS \
  ~/projects/websharks/php-web-server/src/includes/router.php
```

_**Note:** It is normal for the process to remain open (i.e., occupy) your terminal session. While the server is running, you can open a browser and navigate your web-accessible files. Press <kbd>Ctrl-C</kbd> in your terminal window to stop the web server when you're done testing._

_**Note:** The document root starts out with just the `~/www` base, and then automatically becomes: `~/www/localhost` when you visit: `http://localhost:8080/` in a browser. In other words, the document root changes based on the host name in the request, making it possible to run one PHP Web Server against multiple host names w/ multiple document roots that are satisfied dynamically by `router.php`._

## Additional Information

### WordPress-Compatible

The `router.php` file is capable of automatically falling back on a root `index.php` file in your document root sub-folder for each host name (i.e., it performs a `mod_rewrite` simulation). This makes the PHP Web Server compatible with applications such as WordPress also.

_**Limitation:** Standard WordPress only. Not compatible with WordPress Multisite Networking._

### Using a Custom Host Name

If you've edited your `/etc/hosts` file and would like to use a custom host name, that's fine. Simply start the web server with a different host name, and create a document root sub-folder matching that host name.

_**Why `sudo` here?** Most systems require `sudo` to bind to ports lower than `1024`. `sudo` is required in this case, because we want to use the default standard port `80` so we can access the web-accessible files by typing a custom host name: `http://example.dev` (without the port number). Thus, binding to the default port `80` requires `sudo`._

_**Note:** This will only work on port `80` if it is available. For instance, if you're also running Apache, Nginx, or another web server that consumes port `80`, you must stop that server to free-up port `80` for PHP._

```bash
$ sudo php \
  -S example.dev:80 \
  -t ~/www \
  -d variables_order=EGPCS \
  ~/projects/websharks/php-web-server/src/includes/router.php
```

Now, if your `/etc/hosts` are configured properly, you can access:

- `http://example.dev/`

Document root starts as `~/www` and becomes: `~/www/example.dev` when you visit `http://example.dev/`

### One Document Root w/ Multiple Sub-Domains

You can force the document root sub-folder to a fixed location by hard-coding the full document root as follows. Note the addition of `/example.dev` in the `-t` argument below.

_**Why `sudo` here?** Most systems require `sudo` to bind to ports lower than `1024`. `sudo` is required in this case, because we want to use the default standard port `80` so we can access the web-accessible files by typing a custom host name: `http://example.dev` (without the port number). Thus, binding to the default port `80` requires `sudo`._

_**Note:** This will only work on port `80` if it is available. For instance, if you're also running Apache, Nginx, or another web server that consumes port `80`, you must stop that server to free-up port `80` for PHP._

```bash
$ sudo php \
  -S example.dev:80 \
  -t ~/www/example.dev \
  -d variables_order=EGPCS \
  ~/projects/websharks/php-web-server/src/includes/router.php
```

Now, if your `/etc/hosts` are configured properly, you can access:

- `http://example.dev/`
- `http://sub1.example.dev/`
- `http://sub2.example.dev/`

And, the document root is now (always) `~/www/example.dev` so long as your hard-coded document root sub-folder ends w/ the requested root host name. In this case: `example.dev`

The only downside to doing it this way is that you lose the ability to run a single PHP Web Server process that will be capable of locating a nested document root sub-folder based on the host name. In this example, you're hard-coding the document root sub-folder, which limits the PHP Web Server to that document root only; i.e., to a single root host name.

### Environment Variables

The standard PHP environment variables are available in the `$_SERVER` super-global as expected. The WebSharks PHP Web Router will also add some additional environment variables for convenience, and in an effort to fill-in common [FastCGI Params](https://www.nginx.com/resources/wiki/start/topics/examples/phpfcgi/) that would ordinarily be provided by Nginx or another PHP-FPM integration.

Here is an example dump of `$_SERVER` that will give you a quick glimpse. This was produced at the URL: `http://localhost:8080/index.php/path/info?v=1` (with `PATH_INFO` to show a more complete example).

```txt
[HTTP_HOST] => localhost:8080

[DOCUMENT_BASE] => /Users/websharks/www
[DOCUMENT_ROOT] => /Users/websharks/www/localhost

[REMOTE_ADDR] => ::1
[REMOTE_PORT] => 61573

[SERVER_PORT] => 8080
[SERVER_NAME] => localhost
[SERVER_ADDR] => 127.0.0.1
[SERVER_PROTOCOL] => HTTP/1.1
[SERVER_SOFTWARE] => PHP 7.0.4 Development Server

[REQUEST_METHOD] => GET
[REQUEST_SCHEME] => http
[REDIRECT_STATUS] => 200

[QUERY_STRING] => v=1
[PATH_INFO] => /path/info
[SCRIPT_NAME] => /index.php
[DOCUMENT_URI] => /index.php
[PHP_SELF] => /index.php/path/info
[REQUEST_URI] => /index.php/path/info?v=1

[SCRIPT_FILENAME] => /Users/websharks/www/localhost/index.php
[PATH_TRANSLATED] => /Users/websharks/www/localhost/index.php

[REQUEST_TIME_FLOAT] => 1457530515.8927
[REQUEST_TIME] => 1457530515
```

In addition to `$_SERVER`, there is the `$_ENV` super-global, which contains local environment variables from the shell you use to start the PHP Web Server. By default, PHP will automatically _exclude_ the `$_ENV` super-global in the context of the built-in PHP Web Server. However, it is suggested that you include `$_ENV`, as it provides a lot of useful information—such as `USER`, `HOME`, `SHELL`, etc. This is accomplished with the `-d variables_order=EGPCS` flag (note the `E` inclusion) when starting the server.

Whenever `$_ENV` is filled (recommended), the WebSharks Web Router will create an internal copy of all environment variables. It merges `$_SERVER` into `$_ENV` (`$_SERVER` has the highest precedence); and then it will repopulate `$_SERVER` with the full set of environment variables so they can all be accessed from the `$_SERVER` super-global. This is how [most FastCGI implementations](http://php.net/manual/en/ini.core.php#ini.variables-order) work also. It improves compatibility and offers some added convenience.

### MIME Types Library

The following file extensions are automatically served with these content-type headers.

```text
// Text files.
'md'  => 'text/plain; charset=utf-8',
'txt' => 'text/plain; charset=utf-8',

// Log files.
'log' => 'text/plain; charset=utf-8',

// Translation files.
'mo'  => 'application/x-gettext-translation',
'po'  => 'text/x-gettext-translation; charset=utf-8',
'pot' => 'text/x-gettext-translation; charset=utf-8',

// SQL files.
'sql'    => 'text/plain; charset=utf-8',
'sqlite' => 'text/plain; charset=utf-8',

// Template files.
'tmpl' => 'text/plain; charset=utf-8',
'tpl'  => 'text/plain; charset=utf-8',

// Server config files.
'admins'          => 'text/plain; charset=utf-8',
'cfg'             => 'text/plain; charset=utf-8',
'conf'            => 'text/plain; charset=utf-8',
'htaccess'        => 'text/plain; charset=utf-8',
'htaccess-apache' => 'text/plain; charset=utf-8',
'htpasswd'        => 'text/plain; charset=utf-8',
'ini'             => 'text/plain; charset=utf-8',

// CSS/JavaScript files.
'css'  => 'text/css; charset=utf-8',
'js'   => 'application/x-javascript; charset=utf-8',
'json' => 'application/json; charset=utf-8',

// PHP scripts/files.
'php'  => 'text/html; charset=utf-8',
'phps' => 'text/html; charset=utf-8',

// ASP scripts/files.
'asp'  => 'text/html; charset=utf-8',
'aspx' => 'text/html; charset=utf-8',

// Perl scripts/files.
'cgi' => 'text/html; charset=utf-8',
'pl'  => 'text/html; charset=utf-8',

// HTML/XML files.
'dtd'   => 'application/xml-dtd; charset=utf-8',
'hta'   => 'application/hta; charset=utf-8',
'htc'   => 'text/x-component; charset=utf-8',
'htm'   => 'text/html; charset=utf-8',
'html'  => 'text/html; charset=utf-8',
'shtml' => 'text/html; charset=utf-8',
'xhtml' => 'application/xhtml+xml; charset=utf-8',
'xml'   => 'text/xml; charset=utf-8',
'xsl'   => 'application/xslt+xml; charset=utf-8',
'xslt'  => 'application/xslt+xml; charset=utf-8',
'xsd'   => 'application/xsd+xml; charset=utf-8',

// Document files.
'csv'  => 'text/csv; charset=utf-8',
'doc'  => 'application/msword',
'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
'odt'  => 'application/vnd.oasis.opendocument.text',
'pdf'  => 'application/pdf',
'rtf'  => 'application/rtf',
'xls'  => 'application/vnd.ms-excel',

// Image/animation files.
'ai'       => 'image/vnd.adobe.illustrator',
'blend'    => 'application/x-blender',
'bmp'      => 'image/bmp',
'eps'      => 'image/eps',
'fla'      => 'application/vnd.adobe.flash',
'gif'      => 'image/gif',
'ico'      => 'image/x-icon',
'jpe'      => 'image/jpeg',
'jpeg'     => 'image/jpeg',
'jpg'      => 'image/jpeg',
'png'      => 'image/png',
'psd'      => 'image/vnd.adobe.photoshop',
'pspimage' => 'image/vnd.corel.psp',
'svg'      => 'image/svg+xml',
'swf'      => 'application/x-shockwave-flash',
'tif'      => 'image/tiff',
'tiff'     => 'image/tiff',

// Audio files.
'mid'  => 'audio/midi',
'midi' => 'audio/midi',
'mp3'  => 'audio/mp3',
'wav'  => 'audio/wav',
'wma'  => 'audio/x-ms-wma',

// Video files.
'avi'  => 'video/avi',
'flv'  => 'video/x-flv',
'ogg'  => 'video/ogg',
'ogv'  => 'video/ogg',
'mp4'  => 'video/mp4',
'mov'  => 'movie/quicktime',
'mpg'  => 'video/mpeg',
'mpeg' => 'video/mpeg',
'qt'   => 'video/quicktime',
'webm' => 'video/webm',
'wmv'  => 'audio/x-ms-wmv',

// Font files.
'eot'   => 'application/vnd.ms-fontobject',
'otf'   => 'application/x-font-otf',
'ttf'   => 'application/x-font-ttf',
'woff'  => 'application/x-font-woff',
'woff2' => 'application/x-font-woff',

// Archive files.
'7z'   => 'application/x-7z-compressed',
'dmg'  => 'application/x-apple-diskimage',
'gtar' => 'application/x-gtar',
'gz'   => 'application/gzip',
'iso'  => 'application/iso-image',
'jar'  => 'application/java-archive',
'phar' => 'application/php-archive',
'rar'  => 'application/x-rar-compressed',
'tar'  => 'application/x-tar',
'tgz'  => 'application/x-gtar',
'zip'  => 'application/zip',

// Other misc files.
'bat'   => 'application/octet-stream',
'bin'   => 'application/octet-stream',
'class' => 'application/octet-stream',
'com'   => 'application/octet-stream',
'dll'   => 'application/octet-stream',
'exe'   => 'application/octet-stream',
'sh'    => 'application/octet-stream',
'bash'  => 'application/octet-stream',
'zsh'   => 'application/octet-stream',
'so'    => 'application/octet-stream',
```

### Caveats

- `https://` is completely unsupported at this time.

- HTTP 2 is completely unsupported at this time (HTTP 1.1 only).

- PHP's web server runs only one single-threaded process. PHP scripts will stall if a request is blocked; i.e., requests are processed synchronously. One at a time (max). This means, for instance, that you can't have a PHP script that attempts to connect to another PHP script that is also served by the same PHP Web Server. That results in a timeout every time.

  For instance, if `http://localhost:8080/script1.php` contains:

  ```php
  <?php
  file_get_contents('http://localhost:8080/script2.php');
  // `script2.php` will not load because `script1.php` is blocking it.
  // In other words, `script2.php` cannot run until `script1.php` finishes.
  // It can't finish, because it is stuck waiting for `script2.php`.
  ```

  _In short, `script2.php` will never run, and `script1.php` will timeout waiting for it._
