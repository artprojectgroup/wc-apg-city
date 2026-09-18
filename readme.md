# WC - APG City

Contributors: artprojectgroup

Donate link: https://artprojectgroup.es/tienda/donacion

Tags: city, state, postcode, geonames, google maps

Requires at least: 5.1

Tested up to: 7.2

Requires PHP: 7.4

Stable tag: 2.1.2

WC requires at least: 5.6

WC tested up to: 11.1.0

License: GNU General Public License v3 or later

License URI: https://www.gnu.org/licenses/gpl-3.0.html

Añade a WooCommerce un nombre de población automático generado a partir del código postal.

## Description

**WC - APG City** añade a tu tienda WooCommerce un nuevo campo población automático generado a partir del código postal a través de la API de GeoNames o la API de Google Maps.


### Características

- Totalmente compatible con el Bloque de Finalizar compra del editor de bloques de WordPress.
- Dispone de una base de datos local de GeoNames que se descarga y actualiza mensualmente para mejorar el rendimiento y reducir las consultas a las API externas.
- Puedes seleccionar entre la API de GeoNames y la API de Google Maps.
- Debes añadir tu propia Clave de API de Google Maps o usuario de GeoNames.
- Tus credenciales de API nunca se envían al navegador: todas las consultas las hace el servidor.
- Puedes personalizar el texto predeterminado del campo de selección.
- Puedes personalizar el texto de la opción para cambiar a un campo de texto.
- Puedes bloquear la modificación de los campos población y provincia (estado).
- Puedes personalizar el color de fondo de los campos bloqueados.
- En caso de que el código postal sea compartido por más de una población, el cliente podrá seleccionar el nombre de la población correcto del listado devuelto por GeoNames o Google Maps.
- Si la población no está en el listado o no se encuentra en ninguna de las dos API, el cliente puede introducir manualmente el nombre de su población.
- También selecciona la provincia (estado), siempre que el nombre coincida con el obtenido de GeoNames o Google Maps.

### Traducciones

- *English*: por [**Art Project Group**](https://artprojectgroup.es/) (idioma por defecto).
- *Español*: por [**Art Project Group**](https://artprojectgroup.es/).

### Más información

Puedes obtener más información sobre **WC - APG City** en nuestro [sitio web oficial](https://artprojectgroup.es/plugins-para-woocommerce/wc-apg-city) y seguir el desarrollo en [GitHub](https://github.com/artprojectgroup/wc-apg-city).

## Instalación

1. Instala el plugin de una de estas formas:
 - Sube la carpeta `wc-apg-city` al directorio `/wp-content/plugins/` vía FTP.
 - Sube el archivo ZIP completo vía *Plugins -> Añadir nuevo -> Subir* en el panel de administración de WordPress.
 - Busca **WC - APG City** en *Plugins -> Añadir nuevo* y pulsa el botón *Instalar ahora*.
2. Activa el plugin a través del menú *Plugins* en el panel de administración de WordPress.
3. Configura el plugin en *WooCommerce -> City field* o a través del enlace *Ajustes* en la página de plugins.

## Preguntas frecuentes

### ¿Necesita configuración?

Solo necesitas seleccionar entre la API de GeoNames y la API de Google Maps, y añadir tu propia Clave de API de Google Maps o usuario de GeoNames.

### ¿Qué API es mejor?

Depende de muchos factores, pero la que mejores resultados nos ha dado es la API de GeoNames. En cualquier caso, si no se encuentra ningún resultado en la API seleccionada, volverá a buscar de nuevo en la otra API.

### ¿Dónde puedo obtener soporte?

**WC - APG City** es un plugin gratuito. **Art Project Group** no proporciona soporte técnico gratuito, pero ofrece un servicio de [soporte técnico](https://artprojectgroup.es/tienda/ticket-de-soporte) de pago para instalación y configuración.

## Changelog

### 2.1.2

- Corregido: en un checkout clásico el campo de población dejaba de rellenarse, porque el plugin cargaba el script del bloque de Finalizar compra en lugar del clásico.

### 2.1.1

- Corregido: un error fatal en WordPress 5.0; el plugin ya declara que necesita la 5.1.
- La importación de códigos postales descarta un lote mal formado en lugar de lanzar una consulta rota.
- Limpieza de código para pasar el Plugin Check de wordpress.org sin avisos.

### 2.1.0

- Seguridad: la clave de Google Maps y el usuario de GeoNames ya no se envían al navegador.
- Seguridad: los textos personalizables del campo de población ya no pueden inyectar código en el checkout.
- Seguridad: el código postal y el país se validan antes de cada consulta, y las consultas están limitadas por IP.
- Seguridad: el archivo de GeoNames descargado deja de ser accesible públicamente.
- Corregido: los gestores de tienda ya pueden guardar los ajustes.
- Corregido: se rellena la provincia en poblaciones con apóstrofo y en Austria, Francia y Portugal con Google Maps.
- Corregido: el campo de provincia se libera cuando el cliente escribe la población a mano.
- Corregido: el selector de población ya no se descarta al cargar el bloque de Finalizar compra, que ahora funciona también en temas de bloques.
- Corregido: el campo de población funciona en navegadores que no informan de su user agent.
- Corregido: una caída de la API ya no se cachea como «sin resultados».
- Corregido: la base de datos local se vuelve a llenar sola si su tabla se queda vacía, en lugar de quedarse así para siempre.
- El campo de población ya no se modifica en el escritorio, que nunca fue el objetivo del plugin.
- Arreglos menores: color de los campos bloqueados, avisos de PHP en instalación nueva, parpadeo de estilos y limpieza al desinstalar.
- Compatible con WordPress 7.2 y WooCommerce 11.1.0.

### 2.0.4

- Arreglo menor.

### 2.0.3

- Arreglo menor.

### 2.0.2

- Corrección de textos y traducciones.
- Actualización de captura de pantalla.

### 2.0.1

- Añadido un nuevo campo para personalizar el color de fondo de los campos bloqueados.
- Arreglo menor.

### 2.0.0

- Añadida compatibilidad completa con el Bloque de Finalizar compra.
- Nueva base de datos local GeoNames con importación mensual. **Actualización patrocinada por [Bestway](https://bestwaystore.es)**.
- Mejora de rendimiento general.
- Arreglo menor.

### 1.4.0.1

- Arreglo menor.

### 1.4.0

- Añadido un nuevo campo para introducir el usuario de GeoNames.
- Adecuación completa del código a los estándares de seguridad marcados por WordPress.
- Arreglo menor.

### 1.3.0.3

- Arreglo menor.

### 1.3.0.2

- Arreglo menor.

### 1.3.0.1

- Arreglo menor.

### 1.3

- Añadida nueva funcionalidad para bloquear los campos. **Actualización patrocinada por [Gardiun](https://gardiun.com/)**.
- Nuevas funciones y controles JavaScript.
- Actualización de captura de pantalla.

### 1.2.0.2

- Actualización de cabecera.
- Actualización de hoja de estilo.
- Actualización de captura de pantalla.

### 1.2.0.1

- Arreglo menor.

### 1.2

- Añadidos dos nuevos campos para poder personalizar los textos.

### 1.1.0.2

- Arreglo menor.

### 1.1.0.1

- Arreglo menor.

### 1.1

- Reparación de error de JavaScript.

### 1.0.2.1

- Arreglo menor.

### 1.0.2

- Arreglo menor.

### 1.0.1.6

- Arreglo menor.

### 1.0.1.5

- Arreglo menor.

### 1.0.1.4

- Arreglo menor.

### 1.0.1.3

- Arreglo menor.

### 1.0.1.2

- Añadida compatibilidad con WooCommerce 3.4.

### 1.0.1.1

- Actualización de cabecera.
- Actualización de hoja de estilo.
- Actualización de captura de pantalla.

### 1.0.1

- Arreglo menor.
- Nueva captura de pantalla.

### 1.0

- Añadida la Clave de API de Google Maps.
- Añadida búsqueda automática en ambas APIs en caso de no encontrar resultados en la API seleccionada.
- Añadido el reemplazo del campo select por un campo input en caso de no encontrar resultados en ninguna de las dos APIs.
- Añadida opción en el campo select que lo reemplaza por un campo input para que el cliente pueda introducir manualmente su población.
- Arreglos menores.

### 0.3.6.3

- Arreglo de localización.

### 0.3.6.2

- Soporte para instalaciones multisitio.

### 0.3.6.1

- Arreglo del soporte de la API de GeoNames.

### 0.3.6

- Soporte para la API de GeoNames compatible con iThemes Security.

### 0.3.5.1

- Evita el funcionamiento en Microsoft Internet Explorer 11 o anterior.

### 0.3.5

- Añadido JavaScript a los campos de formulario de Mi cuenta.

### 0.3.4

- Añadida autoapertura del campo de selección con múltiples poblaciones.

### 0.3.3

- Se han corregido errores de JavaScript.

### 0.3.2

- Clonación de la clase CSS original del campo.

### 0.3.1

- Arreglo de incompatibilidad con sitios web con certificado SSL.

### 0.3

- Añadido soporte para la API de GeoNames.
- Nueva pantalla de configuración para seleccionar API.
- Nueva captura de pantalla.

### 0.2.1

- Nueva llamada a la API de Google Maps.

### 0.2

- Se han corregido errores de JavaScript.
- Se ha añadido la selección del nombre de la provincia (estado).

### 0.1

- Versión inicial.

## Gracias

Gracias a todos los que usáis el plugin, ayudáis a mejorarlo, hacéis donaciones o nos animáis con vuestros comentarios.

Si te resulta útil, puedes apoyar su desarrollo con una [pequeña donación](https://artprojectgroup.es/tienda/donacion).

## Servicios externos

1. A los servicios de GeoNames, para descargar y actualizar mensualmente la base de datos local completa de poblaciones y códigos postales, así como para realizar consultas a su API cuando no exista información en la base de datos local.
 - Envía el país y el código postal.
 - Más información: https://www.geonames.org/export/

2. A la API de Google Maps, para obtener el nombre de la población y la provincia a partir del código postal y el país cuando se selecciona esta opción en la configuración del plugin.
 - Envía el país y el código postal.
 - Más información: https://policies.google.com/privacy
