<?php


namespace RensrImprimirFactura\Controller\Admin;

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Response;
use PrestaShop\PrestaShop\Adapter\Module\Module;

use Module as LegacyModule;

class TFacturaController extends FrameworkBundleAdminController
{
    public function indexAction($orderId)
    {
        // Lógica para manejar la acción
        $module = LegacyModule::getInstanceByName('rensr_imprimir_factura');



        return new Response($module->imprimirFactura($orderId), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="factura_' . $orderId . '.pdf"',
        ]);


    }
}