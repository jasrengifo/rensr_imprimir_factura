$(() => {
    $(".descargar-factura").each((index, element) => {
        // Crea un objeto URL para analizar
        let urlObj = new URL($(element).attr('href'), window.location.origin); // Agregamos el origen para que sea un URL completo


        let pathSegments = urlObj.pathname.split('/');

        let idCart = pathSegments[pathSegments.length - 2];

        $.post($(element).attr('href'), { check: 1, id_cart: idCart })  // Enviamos 'check' en el cuerpo
            .then((response) => {
                // Por ejemplo, marcamos el ícono con color verde cuando la solicitud AJAX tiene éxito
                if(response=="true"){
                    $(element).addClass("factura-creada");
                }
            });
    });

    $(".descargar-factura").on('click', (event) => {
        const element = event.currentTarget;
        $(element).addClass("factura-creada");
    });


});