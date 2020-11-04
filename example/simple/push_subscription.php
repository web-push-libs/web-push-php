<?php

declare(strict_types=1);

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Minishlink\WebPush\Subscription;

require __DIR__.'/../../vendor/autoload.php';

$subscription = Subscription::createFromString(file_get_contents('php://input'));
var_dump($subscription);

//{"endpoint":"https://updates.push.services.mozilla.com/wpush/v2/gAAAAABfc…Z7Ow5rdpfhmi4FVi4vdkSDnS-LbBK0KamOyJzCNcBx5Fquy6SQruel_AGR6Q","keys":{"auth":"Be8NNQZmzet31pe-cIQYJw","p256dh":"BNCimyX7rUgmibAn5KVaSDiGlBX5NrxSphvgk21tgmGi5ga6EwVE9Odz5BlJGq_RPfKE3HnZKGu3fkOSF-mvwtc"}}
