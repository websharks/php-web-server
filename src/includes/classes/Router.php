<?php
namespace WebSharks\PhpWebServer\Classes;

use WebSharks\PhpWebServer\Classes;
use WebSharks\PhpWebServer\Interfaces;
use WebSharks\PhpWebServer\Traits;

/**
 * Router.
 *
 * @since 160308
 */
class Router
{
    /**
     * Doc root.
     *
     * @since 160308
     *
     * @type string &`DOCUMENT_ROOT`.
     */
    protected static $root;

    /**
     * Host name.
     *
     * @since 160308
     *
     * @type string `HTTP_HOST` (minus port).
     */
    protected static $host;

    /**
     * Full relative path.
     *
     * @since 160308
     *
     * @type string &`PHP_SELF`.
     */
    protected static $full_path;

    /**
     * Relative path w/o path info.
     *
     * @since 160308
     *
     * @type string &`SCRIPT_NAME|DOCUMENT_URI`.
     */
    protected static $path;

    /**
     * Path info.
     *
     * @since 160308
     *
     * @type string &`PATH_INFO`.
     */
    protected static $path_info;

    /**
     * File path.
     *
     * @since 160308
     *
     * @type string &`SCRIPT_FILENAME|PATH_TRANSLATED`.
     */
    protected static $file;

    /**
     * File ext.
     *
     * @since 160308
     *
     * @type string
     */
    protected static $ext;

    /**
     * Response.
     *
     * @since 160308
     *
     * @type string|bool
     */
    public static $response;

    /**
     * Version.
     *
     * @since 160308
     *
     * @type string
     */
    const VERSION = '160322'; //v//

    /**
     * Router response.
     *
     * @since 160308 PHP CLI server.
     *
     * @return string|bool Response.
     */
    public static function route()
    {
        if (isset(static::$response)) {
            return static::$response;
        }
        # Merge environment vars.

        $_SERVER = array_merge($_ENV, $_SERVER);
        // Like FastCGI. See: <http://jas.xyz/1LcxXIx>

        # Setup document root & path-related environment vars.
        # Also bind a few super-globals to route-related properties.
        # This is like FastCGI. See: <http://jas.xyz/1LcJeJ7>

        static::$root             = $_SERVER['DOCUMENT_ROOT'];
        static::$root             = preg_replace('/[\\\\\/]+$/u', '', static::$root);
        static::$host             = preg_replace('/\:[0-9]+/u', '', $_SERVER['HTTP_HOST']);
        $_SERVER['DOCUMENT_BASE'] = $_SERVER['DOCUMENT_ROOT'] = &static::$root;

        if (!preg_match('/'.preg_quote(static::$host, '/').'$/ui', static::$root)) {
            static::$root .= DIRECTORY_SEPARATOR.static::$host;
        }
        $_SERVER['PHP_SELF'] = &static::$full_path;

        $_SERVER['SCRIPT_NAME']  = &static::$path;
        $_SERVER['DOCUMENT_URI'] = &static::$path;

        $_SERVER['PATH_INFO'] = &static::$path_info;

        $_SERVER['SCRIPT_FILENAME'] = &static::$file;
        $_SERVER['PATH_TRANSLATED'] = &static::$file;

        # Fill-in environment vars missing in PHP CLI server.
        # This is like FastCGI. See: <http://jas.xyz/1LcJeJ7>

        if (!isset($_SERVER['REDIRECT_STATUS'])) {
            $_SERVER['REDIRECT_STATUS'] = 200; # Always 200.
        }
        if (!isset($_SERVER['REQUEST_SCHEME'])) {
            $_SERVER['REQUEST_SCHEME'] = 'http'; # `https` not possible.
        }
        if (!isset($_SERVER['SERVER_ADDR']) && !empty($_SERVER['SERVER_NAME'])) {
            $_SERVER['SERVER_ADDR'] = gethostbyname($_SERVER['SERVER_NAME']);
        }
        # Identify the WebSharks router.

        $_SERVER['SERVER_SOFTWARE'] .= '; via WebSharks router.';

        # Fill file and path-related vars now.
        # This is like FastCGI. See: <http://jas.xyz/1LcJeJ7>

        static::$full_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        static::$path      = static::$full_path;
        static::$path_info = '';

        if (preg_match('/^(?<path>.+?\.php)(?<path_info>\/.*)$/u', static::$full_path, $_m)) {
            // This is like `fastcgi_split_path_info`. See: <http://jas.xyz/1LcIbca>
            static::$path      = $_m['path'];
            static::$path_info = $_m['path_info'];
        } // unset($_m); // Housekeeping.

        static::propagatePath(static::$path); // `file` & `ext`.
        // This sets `file` & `ext` as a part of the propagation.

        # Route this request and return response.

        if ((static::$response = static::toFile()) !== null) {
            return static::$response;
        } elseif ((static::$response = static::toDirIndex()) !== null) {
            return static::$response;
        } elseif ((static::$response = static::toStatic404Error()) !== null) {
            return static::$response;
        } elseif ((static::$response = static::toRootPhpIndex()) !== null) {
            return static::$response;
        }
        return static::$response = static::to404Error();
    }

    /**
     * File response.
     *
     * @since 160308 PHP CLI server.
     *
     * @return null|string|bool Response.
     */
    protected static function toFile()
    {
        if (is_file(static::$file)) {
            if (static::$ext === 'php') {
                static::noCacheHeaders();
                static::contentTypeHeader();
                chdir(dirname(static::$file));
                return static::$file;
            } else {
                static::cacheHeaders();
                static::contentTypeHeader();
                readfile(static::$file);
                return true;
            }
        }
    }

    /**
     * Directory index response.
     *
     * @since 160308 PHP CLI server.
     *
     * @return null|string|bool Response.
     */
    protected static function toDirIndex()
    {
        if (is_dir(static::$file)) {
            foreach (['index.html', 'index.php'] as $_index) {
                if (is_file(static::$file.'/'.$_index)) {
                    static::propagatePath(static::$path.'/'.$_index);
                    return static::toFile();
                }
            } // unset($_index);
        }
    }

    /**
     * Static 404 response.
     *
     * @since 160308 PHP CLI server.
     *
     * @return null|string|bool Response.
     */
    protected static function toStatic404Error()
    {
        if (static::$ext && !empty(static::$mime_types[static::$ext])) {
            if (static::$ext !== 'php') {
                return static::to404Error();
            }
        }
    }

    /**
     * Root PHP index response.
     *
     * @since 160308 PHP CLI server.
     *
     * @return null|string|bool Response.
     */
    protected static function toRootPhpIndex()
    {
        if (is_file(static::$root.'/index.php')) {
            static::propagatePath('/index.php');
            return static::toFile();
        }
    }

    /**
     * 404 error response.
     *
     * @since 160308 PHP CLI server.
     *
     * @return bool Always a `true` response.
     */
    protected static function to404Error()
    {
        http_response_code(404);
        static::noCacheHeaders();
        static::contentTypeHeader('txt');
        echo '404 Error: File Not Found!';
        return true;
    }

    /**
     * Propagate path.
     *
     * @since 160308 PHP CLI server.
     *
     * @return string $path Path to propagate.
     */
    protected static function propagatePath($path)
    {
        static::$path      = $path; # Update & propagate.
        static::$full_path = static::$path.static::$path_info;
        static::$file      = static::$root.static::$path;
        static::$file      = preg_replace('/\/+$/u', '', static::$file);
        static::$ext       = static::ext(static::$file);
    }

    /**
     * Extension.
     *
     * @since 160308 PHP CLI server.
     *
     * @return string File extension.
     */
    protected static function ext($file)
    {
        return mb_strtolower(ltrim(mb_strrchr(basename($file), '.'), '.'));
    }

    /**
     * Cache headers.
     *
     * @since 160308 PHP CLI server.
     */
    protected static function cacheHeaders()
    {
        header('expires: '.gmdate(DATE_RFC1123, time() + 691200));
        header('cache-control: max-age=691200');
    }

    /**
     * No-cache headers.
     *
     * @since 160308 PHP CLI server.
     */
    protected static function noCacheHeaders()
    {
        header('expires: '.gmdate(DATE_RFC1123, time() - 691200));
        header('cache-control: no-cache, must-revalidate, max-age=0');
    }

    /**
     * Content-type header.
     *
     * @since 160308 PHP CLI server.
     *
     * @param string $ext Defaults to current ext.
     */
    protected static function contentTypeHeader($ext = null)
    {
        $ext = isset($ext) ? $ext : static::$ext;
        if ($ext && !empty(static::$mime_types[$ext])) {
            header('content-type: '.static::$mime_types[$ext]);
        }
    }

    /**
     * MIME types array.
     *
     * @since 160308 PHP CLI server.
     *
     * @type array Extension => content type.
     */
    protected static $mime_types = [
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
    ];
}
