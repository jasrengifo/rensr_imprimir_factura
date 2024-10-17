<?php

namespace RensrImprimirFactura\Classes;

require_once _PS_MODULE_DIR_ . 'rensr_imprimir_factura/fpdf.php';


class PDFF extends FPDF
{
    // Encabezado personalizado
    function Header()
    {
        // Aquí puedes incluir el fondo preimpreso si es necesario.
//        $this->Image(__DIR__.'/views/img/plantilla.jpg', 0, 0, 210); // Si necesitas mostrar una imagen de fondo (A4 = 210 mm)
    }

    private function centerX($x_center, $text){

        // Obtener el ancho del texto actual en la fuente establecida
        $text_width = $this->GetStringWidth($text);

        // Calcular la nueva posición X restando la mitad del ancho del texto
        $x_position = $x_center - ($text_width / 2);

        return $x_position;
    }

    function AddCustomContent($data)
    {
        $this->SetFont('Arial', 'B', 18);
        $this->SetXY(142, 10);  // Posición de la dirección
        $this->Cell(40, 10, "FACTURA");



        $this->SetFont('Arial', '', 10);


        //Datos de la empresa
        $this->SetXY($this->centerX(70, $data['nombre_fiscal']), 10);
        $this->Cell(40, 10, $data['nombre_fiscal']);


        $this->SetXY($this->centerX(70, $data['nif']), 10);
        $this->Cell(40, 20, $data['nif']);

        $this->SetXY($this->centerX(70, $data['calle']), 15);
        $this->Cell(40, 20, $data['calle']);

        $this->SetXY($this->centerX(70, $data['codigo_postal'] . " - ".$data['localidad']." - ".$data['ciudad']), 20);
        $this->Cell(40, 20, $data['codigo_postal'] . " - ".$data['localidad']." - ".$data['ciudad']);

        $this->SetXY($this->centerX(70, "www.pcdiez.com"), 25);
        $this->Cell(40, 20, "www.pcdiez.com");

        //Datos de la Factura
        $this->SetXY(73, 45);
        $this->Cell(40, 06, $data['fecha']);

        $this->SetXY(26, 45);
        $this->Cell(40, 06, $data['n_factura']);

        //Datos del cliente
        $this->SetFont('Arial', 'B', 11);
        $this->SetXY(107, 28);
        $this->Cell(40, 10, $data['cliente_nombre']);

        $this->SetFont('Arial', '', 11);
        $this->SetXY(107, 33);
        $this->Cell(40, 10, $data['cliente_direccion']);

        $this->SetXY(107, 39);
        $this->Cell(40, 10, $data["cliente_cod_postal"]." ".$data["cliente_ciudad"]);

        $this->SetXY(107, 44);
        $this->Cell(40, 10, $data["cliente_NIF"]);


        $ycood = 66;
        foreach($data['productos'] as $p)
        {

            $this->SetXY(9, $ycood+2);
            $this->MultiCell(25, 5, $p['ref'], 0, 'L', false);


//            $this->Rect(34, $ycood, 40, 10, 'D');

            $this->SetXY(34, $ycood+2);
            $this->MultiCell(100, 5, $p['desc'], 0, 'L', false);

            $this->SetXY(139, $ycood);
            $this->Cell(40, 10, $p['cant']);

            $this->SetXY(149, $ycood);
            $this->Cell(40, 10, $p['precio']);

            $this->SetXY(167, $ycood);
            $this->Cell(40, 11, $p['dto']);

            $this->SetXY(175, $ycood);
            $this->Cell(40, 10, $p['iva_perc']);

            $this->SetXY(183, $ycood);
            $this->Cell(40, 10, $p['importe']);

            $ycood += 18;
        }



        //Datos footer (totales)
        $this->SetXY(10, 250);
        $this->Cell(40, 10, $data['descuento']);

        $this->SetXY($this->centerX(67, $data['base_imponible']), 254);
        $this->Cell(40, 10, $data['base_imponible']);

        $this->SetXY($this->centerX(92, $data['iva_percentage']), 254);
        $this->Cell(40, 10, $data['iva_percentage']);

        $this->SetXY($this->centerX(111, $data['iva_value']), 254);
        $this->Cell(40, 10, $data['iva_value']);


        //Esto es el campo RE y el cliente lo pidió vacío por Whatsapp 15/10/2024
//                $this->SetXY($this->centerX(125, "0"), 257);
//                $this->Cell(40, 10, "0");
//                $this->SetXY($this->centerX(145, "0,00"), 257);
//                $this->Cell(40, 10, "0,00");

        $this->SetFont('Arial', 'B', 12);
        $this->SetXY($this->centerX(178, $data['total']." Euros"), 254);
        $this->Cell(40, 10, $data['total']." Euros");

        // Añadir más campos según las coordenadas que necesites
    }
}

?>