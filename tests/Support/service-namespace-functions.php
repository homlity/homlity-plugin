<?php

declare(strict_types=1);

namespace Homlity\PluginInmobiliario\Services;

use Homlity\PluginInmobiliario\Tests\Support\WpStubs;

/**
 * Las cookies se interceptan dentro del espacio de nombres del servicio.
 *
 * PHP resuelve una llamada sin cualificar primero contra el espacio de nombres
 * actual, así que definir aquí `setcookie()` sustituye a la de PHP sin tocar el
 * código de producción. Sin esto no habría forma de comprobar la ventana de
 * 24 h, que se apoya justo en esas cookies.
 */
function setcookie(string $name, string $value = '', array|int $options = 0): bool
{
    WpStubs::$cookiesSet[$name] = ['value' => $value, 'options' => $options];
    $_COOKIE[$name] = $value;

    return true;
}

function headers_sent(?string &$file = null, ?int &$line = null): bool
{
    return WpStubs::$headersSent;
}
