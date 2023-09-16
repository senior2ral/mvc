<?php
namespace Mvc\Session\Adapter;

use Mvc\Session\Adapter;
use Mvc\Session\AdapterInterface;

/**
 * Mvc\Session\Adapter\Files
 *
 * This adapter store sessions in plain files
 *
 *<code>
 * $session = new Mvc\Session\Adapter\Files(array(
 *    'uniqueId' => 'my-private-app'
 * ));
 *
 * $session->start();
 *
 * $session->set('var', 'some-value');
 *
 * echo $session->get('var');
 *</code>
 */
class Files extends Adapter implements AdapterInterface
{
}
