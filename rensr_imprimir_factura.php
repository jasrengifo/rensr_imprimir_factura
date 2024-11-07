<?php
        if (!defined('_PS_VERSION_')) {
            exit;
        }

        use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollectionInterface;
        use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
        use PrestaShop\PrestaShop\Core\Grid\Column\ColumnInterface;
        use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
        use PrestaShop\PrestaShop\Core\Grid\Exception\ColumnNotFoundException;

        require_once __DIR__ . '/classes/PDF.php';

        use RensrImprimirFactura\Classes\PDFF;


        //use Column
        class rensr_imprimir_factura extends \Module
        {
            public $adminControllers = [
                'adminRensrFacturass' => 'AdminRensrFacturass',
            ];

            public function __construct()
            {
                $this->name = 'rensr_imprimir_factura';
                $this->tab = 'front_office_features';
                $this->version = '1.0.3';
                $this->author = 'rensr.pt';
                $this->need_instance = 0;
                $this->ps_versions_compliancy = ['min' => '1.7', 'max' => _PS_VERSION_];
                $this->bootstrap = true;
                $this->controllers = array('process');

                parent::__construct();

                $this->displayName = $this->l('Imprimir factura con modelo pre-impreso');
                $this->description = $this->l('Módulo personalizado para imprimir facturas con un modelo pre-impreso');
                $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
            }

            public function install()
            {
                if (!parent::install() || !$this->registerHook('actionOrderGridDefinitionModifier') || !$this->installDb() || !$this->registerHook('header') || !$this->registerHook('ActionAdminControllerSetMedia')) {
                    return false;
                }

                return true;
            }


            public function hookActionAdminControllerSetMedia(array $params)
            {
                // check if it is orders controller
                if ($this->context->controller->controller_name !== 'AdminOrders') {
                    return;
                }
                $action = Tools::getValue('action');

                // check if it is orders index page (we want to skip if it is `order create` or `order view` page)
                if ($action === 'vieworder' || $action === 'addorder') {
                    return;
                }

                $this->context->controller->addJS('modules/' . $this->name . '/views/js/main.js');

                //agregar css
                $this->context->controller->addCSS($this->_path . 'views/css/main.css');

            }

            public function hookActionOrderGridDefinitionModifier(array $params): void
            {


                /** @var GridDefinitionInterface $orderGridDefinition */
                $orderGridDefinition = $params['definition'];

                /** @var RowActionCollectionInterface $actionsCollection */
                $actionsCollection = $this->getActionsColumn($orderGridDefinition)->getOptions()['actions'];


                $actionsCollection->add(

                    (new LinkRowAction('facturaa'))
                        ->setName($this->trans('Impr Factura', [], 'Modules.RensrImprimirFactura.Admin'))
                        ->setIcon('picture_as_pdf')
                        ->setOptions([
                            'route' => 'rensrimprimirfactura_factura_route',
                            'route_param_name' => 'orderId',
                            'route_param_field' => 'id_order',
                            'use_inline_display' => true,
                            'target' => '_blank',
                            'attr' => [
                                'class' => 'descargar-factura', //
                            ],
                        ])
                );



            }

            private function getActionsColumn($gridDefinition)
            {
                try {
                    /** @var ColumnInterface $column */
                    foreach ($gridDefinition->getColumns() as $column) {
                        if ('actions' === $column->getId()) {
                            return $column;
                        }
                    }
                } catch (ColumnNotFoundException $e) {
                    // It is possible that not every grid will have actions column.
                    // In this case you can create a new column or throw exception depending on your needs
                    throw $e;
                }
            }


            private function installDb() {
                $sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'rensr_factura` (
                      `id_rensr_factura` INT(11) NOT NULL AUTO_INCREMENT,
                      `id_order` INT(11) NOT NULL,
                      `nombre_fiscal` VARCHAR(255) NOT NULL,
                      `nif` VARCHAR(255) NOT NULL,
                      `calle` VARCHAR(255) NOT NULL,
                      `cod_postal` VARCHAR(255) NOT NULL,
                      `localidad` VARCHAR(255) NOT NULL,
                      `ciudad` VARCHAR(255) NOT NULL,
                      `prefijo_factura` VARCHAR(255) NOT NULL,
                      `n_factura` VARCHAR(255) NOT NULL,
                      `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                      PRIMARY KEY (`id_rensr_factura`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

                return Db::getInstance()->execute($sql);
            }

            public function uninstall()
            {
                if (!parent::uninstall()) {
                    return false;
                }
                return true;
            }

            public function hookHeader()
            {
                $this->context->controller->addJS($this->_path.'views/js/main.js');
                $this->context->controller->addCSS($this->_path.'views/css/main.css');
            }




            private function formatearNFactura($number) {
                // Obtén el prefijo de la factura
                $prefijo = Configuration::get('RENSR_PRE_FACTURA');

                // Obtén el número de caracteres que debe tener el número de factura (rellenado con ceros si es necesario)
                $chars_factura = intval(Configuration::get('RENSR_CHARS_FACTURA'));

                // Verifica que el valor de $chars_factura sea mayor a 0, para evitar errores
                if ($chars_factura > 0) {
                    // Rellenar con ceros a la izquierda
                    $n_factura = str_pad($number, $chars_factura, '0', STR_PAD_LEFT);
                } else {
                    // Si $chars_factura es inválido o no está configurado correctamente, usar el número tal cual
                    $n_factura = $number;
                }

                // Retornar el formato de la factura con prefijo
                return $prefijo . "" . $n_factura;
            }

            public function ObtenerFactura($id_order)
            {
                $sql = "SELECT * FROM "._DB_PREFIX_."rensr_factura WHERE id_order = ".(int)$id_order.";";
                return Db::getInstance()->executeS($sql);
            }


            private function CrearFactura($id_order){
                $n_fiscal = Configuration::get('RENSR_NOMBRE_FISCAL');
                $nif = Configuration::get('RENSR_NIF');
                $calle = Configuration::get('RENSR_CALLE');
                $cod_postal = Configuration::get('RENSR_COD_POSTAL');
                $localidad = Configuration::get('RENSR_LOCALIDAD');
                $ciudad = Configuration::get('RENSR_CIUDAD');
                $pre_factura = Configuration::get('RENSR_PRE_FACTURA');
                $n_factura = (int)Configuration::get('RENSR_N_FACTURA');
                $fecha_factura = Configuration::get('RENSR_FECHA_FACTURA');


                // Construir la query con todas las variables
                $sql = "INSERT INTO "._DB_PREFIX_."rensr_factura 
                        (id_order, nombre_fiscal, nif, calle, cod_postal, localidad, ciudad, prefijo_factura, n_factura, fecha) 
                        VALUES 
                        (
                        '".$id_order."',
                        '".$n_fiscal."',
                         '".$nif."', 
                         '".$calle."', 
                         '".$cod_postal."', 
                         '".$localidad."', 
                         '".$ciudad."', 
                         '".$pre_factura."', 
                         '".$n_factura."', 
                         '".$fecha_factura."');";

                $insert = Db::getInstance()->execute($sql);

                if($insert){
                    (int)Configuration::updateValue('RENSR_N_FACTURA', $n_factura+1);
                }

                return $insert;
            }


            public function imprimirFactura($id_order){

                $factura = $this->ObtenerFactura($id_order);

                if($factura==false || count($factura)<1)
                {
                    $this->crearFactura($id_order);
                }

                $factura = $this->ObtenerFactura($id_order);

                $order = new Order($id_order);

                if($order==false || $order==null){
                    return $this->l('Error al obtener la factura de esta factura');
                }

                $productos = $order->getProducts();


                $pp = Array();
                foreach ($productos as $p) {


                    $pptemp = Array();
                    $pptemp['ref'] = utf8_decode($p['reference']);

                    $pptemp['desc'] = utf8_decode($p['product_name']);

                    $pptemp['cant'] = $p['product_quantity'];

                    $pptemp['precio'] = round((float)$p['unit_price_tax_excl'], 2);

                    $pptemp['dto'] = round((float)$p['discount_quantity_applied'],2);

                    $pptemp['iva_perc'] = round((float)$p['tax_rate']);

                    $pptemp['importe'] = round((float)$p["total_price_tax_excl"], 2);

                    $pp[] = $pptemp;

                }


                //Agregar linea del envio

                $pptemp = Array();
                $pptemp['ref'] = $this->l('Envío');

                //Nombre de la transportadora
                $carrier_id = $order->id_carrier;
                $carrier = new Carrier($carrier_id);
                $pptemp['desc'] = $carrier->name;

                $pptemp['cant'] = 1;

                $pptemp['precio'] = round((float)$order->total_shipping_tax_excl, 2);

                $pptemp['dto'] = 0;

                $pptemp['iva_perc'] = round((float)$order->carrier_tax_rate, 0);

                $pptemp['importe'] = round((float)$order->total_shipping_tax_excl, 2);

                $pp[] = $pptemp;

                //Datos del cliente de facturacion
                $invoice_address = new Address($order->id_address_invoice);

                $pdf = new PDFF();

                $factura = $factura[0];

                if($invoice_address->dni=='' || $invoice_address->dni==null) {
                    $nif = $invoice_address->vat_number;
                }else {
                    $nif = $invoice_address->dni;
                }

                $array = Array(
                    "nombre_fiscal" => utf8_decode($factura['nombre_fiscal']),
                    "nif" => utf8_decode($factura['nif']),
                    "calle" => utf8_decode($factura['calle']),
                    "codigo_postal" => utf8_decode($factura['cod_postal']),
                    "localidad" => utf8_decode($factura['localidad']),
                    "ciudad" => utf8_decode($factura['ciudad']),
                    "fecha" => utf8_decode(date('d/m/Y', strtotime($factura['fecha']))),
                    "n_factura" => utf8_decode($this->formatearNFactura((int)$factura['n_factura'])),
                    "productos" => $pp,
                    "descuento" => round((float)$order->total_discounts, 2),
                    "base_imponible" => round((float)$order->total_paid_tax_excl, 2),
                    "iva_value" => round((float)$order->total_paid_tax_incl-(float)$order->total_paid_tax_excl, 2),
                    "iva_percentage" => round(21, 2),
                    "total" => round((float)$order->total_paid, 2),

                    "cliente_nombre" => utf8_decode(mb_strtoupper($invoice_address->firstname." ".$invoice_address->lastname." ".$invoice_address->company, 'UTF-8')),

                    "cliente_direccion" => utf8_decode(mb_strtoupper($invoice_address->address1, 'UTF-8')),
                    "cliente_cod_postal" => utf8_decode($invoice_address->postcode),
                    "cliente_ciudad" => utf8_decode(mb_strtoupper($invoice_address->city, 'UTF-8')),
                    "cliente_NIF" => utf8_decode(mb_strtoupper($nif, 'UTF-8')),
                    );

                $pdf->AddPage();
                $pdf->AddCustomContent($array);
                $pdfOutput = $pdf->Output('', 'S');
                return $pdfOutput;

            }


            public function getContent()
            {
                $output = null;

                if (Tools::isSubmit('submit'.$this->name)) {
                    $nombre_fiscal = strval(Tools::getValue('RENSR_NOMBRE_FISCAL'));
                    $nif = strval(Tools::getValue('RENSR_NIF'));
                    $calle = strval(Tools::getValue('RENSR_CALLE'));
                    $cod_postal = strval(Tools::getValue('RENSR_COD_POSTAL'));
                    $localidad = strval(Tools::getValue('RENSR_LOCALIDAD'));
                    $ciudad = strval(Tools::getValue('RENSR_CIUDAD'));
                    $pre_factura = strval(Tools::getValue('RENSR_PRE_FACTURA'));
                    $fecha_factura = strval(Tools::getValue('RENSR_FECHA_FACTURA'));
                    $n_factura = intval(Tools::getValue('RENSR_N_FACTURA'));
                    $chars_factura = intval(Tools::getValue('RENSR_CHARS_FACTURA'));

                    // Actualizar todos los valores
                    Configuration::updateValue('RENSR_NOMBRE_FISCAL', $nombre_fiscal);
                    Configuration::updateValue('RENSR_NIF', $nif);
                    Configuration::updateValue('RENSR_CALLE', $calle);
                    Configuration::updateValue('RENSR_COD_POSTAL', $cod_postal);
                    Configuration::updateValue('RENSR_LOCALIDAD', $localidad);
                    Configuration::updateValue('RENSR_CIUDAD', $ciudad);
                    Configuration::updateValue('RENSR_PRE_FACTURA', $pre_factura);
                    Configuration::updateValue('RENSR_FECHA_FACTURA', $fecha_factura);
                    Configuration::updateValue('RENSR_N_FACTURA', $n_factura);
                    Configuration::updateValue('RENSR_CHARS_FACTURA', $chars_factura);

                    $output .= $this->displayConfirmation($this->l('Settings updated'));
                }

                return $output.$this->getForm();
            }

            private function getForm()
            {
                $default_lang = (int)Configuration::get('PS_LANG_DEFAULT');


                $fields_form[0]['form'] = [
                    'legend' => [
                        'title' => $this->l('Configuraciones base'),
                    ],
                    'input' => [
                        [
                            'type' => 'text',
                            'label' => $this->l('Nombre fiscal'),
                            'name' => 'RENSR_NOMBRE_FISCAL',
                            'size' => 20,
                            'required' => true
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('NIF'),
                            'name' => 'RENSR_NIF',
                            'size' => 20,
                            'required' => true
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Calle'),
                            'name' => 'RENSR_CALLE',
                            'size' => 20,
                            'required' => true
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Cod. Postal'),
                            'name' => 'RENSR_COD_POSTAL',
                            'size' => 20,
                            'required' => true
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Localidad'),
                            'name' => 'RENSR_LOCALIDAD',
                            'size' => 20,
                            'required' => true
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Ciudad'),
                            'name' => 'RENSR_CIUDAD',
                            'size' => 20,
                            'required' => true
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Prefijo de factura'),
                            'name' => 'RENSR_PRE_FACTURA',
                            'size' => 20,
                            'required' => true
                        ],
                        [
                            'type' => 'date',
                            'label' => $this->l('Fecha nuevas facturas'),
                            'name' => 'RENSR_FECHA_FACTURA',
                            'size' => 20,
                            'required' => true
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('N Actual de factura'),
                            'name' => 'RENSR_N_FACTURA',
                            'size' => 20,
                            'required' => true
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Cantidad de caracteres para factura'),
                            'name' => 'RENSR_CHARS_FACTURA',
                            'size' => 20,
                            'required' => true
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save'),
                        'class' => 'btn btn-default pull-right'
                    ]
                ];

                $helper = new HelperForm();
                $helper->module = $this;
                $helper->name_controller = $this->name;
                $helper->token = Tools::getAdminTokenLite('AdminModules');
                $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
                $helper->title = $this->displayName;
                $helper->show_toolbar = true;
                $helper->toolbar_scroll = true;
                $helper->submit_action = 'submit'.$this->name;
                $helper->default_form_language = $default_lang;
                $helper->allow_employee_form_lang = $default_lang;

                // Asignar los valores para el formulario
                $helper->fields_value['RENSR_NOMBRE_FISCAL'] = Configuration::get('RENSR_NOMBRE_FISCAL');
                $helper->fields_value['RENSR_NIF'] = Configuration::get('RENSR_NIF');
                $helper->fields_value['RENSR_CALLE'] = Configuration::get('RENSR_CALLE');
                $helper->fields_value['RENSR_COD_POSTAL'] = Configuration::get('RENSR_COD_POSTAL');
                $helper->fields_value['RENSR_LOCALIDAD'] = Configuration::get('RENSR_LOCALIDAD');
                $helper->fields_value['RENSR_CIUDAD'] = Configuration::get('RENSR_CIUDAD');
                $helper->fields_value['RENSR_PRE_FACTURA'] = Configuration::get('RENSR_PRE_FACTURA');
                $helper->fields_value['RENSR_FECHA_FACTURA'] = Configuration::get('RENSR_FECHA_FACTURA');
                $helper->fields_value['RENSR_N_FACTURA'] = Configuration::get('RENSR_N_FACTURA');
                $helper->fields_value['RENSR_CHARS_FACTURA'] = Configuration::get('RENSR_CHARS_FACTURA');

                return $helper->generateForm($fields_form);
            }


        }


