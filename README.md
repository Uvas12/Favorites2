# Favorites2

**Favorites2** es una extensión para MediaWiki que permite a los usuarios registrados guardar páginas en una lista personal de favoritos.

La extensión añade un botón de favorito a las páginas del wiki y proporciona una página especial, `Especial:Favorites`, donde cada usuario puede consultar, editar o eliminar sus páginas favoritas.

Favorites2 funciona como una lista de favoritos simple e independiente de la lista de seguimiento.

## Características

- Permite añadir páginas a favoritos.
- Permite quitar páginas de favoritos.
- Añade una página especial: `Especial:Favorites`.
- Muestra los favoritos agrupados por namespace.
- Ordena los favoritos alfabéticamente.
- Soporta AJAX para añadir y quitar favoritos sin recargar la página.
- Muestra notificaciones de éxito o error.
- Incluye modo icono con corazón.
- Incluye modo texto dentro del menú **Más**.
- Puede añadir un enlace personal a favoritos en el menú de usuario.
- Evita iconos duplicados al usar vista previa con WikiEditor.
- El icono también puede mostrarse en modo edición.
- Limpia registros de favoritos cuando una página se elimina.
- Incluye compatibilidad con borrado permanente usando DeletePagesForGood.
- Mantiene favoritos correctamente cuando una página se renombra.
- Mantiene favoritos correctamente cuando una página se mueve a otro namespace.
- Guarda favoritos por `page_id`, no por título.

## Requisitos

- MediaWiki 1.45 o superior.
- PHP 8.3 o superior.

## Instalación

1. Descarga o clona esta extensión dentro de la carpeta `extensions/` de tu instalación de MediaWiki:

```bash
cd extensions
git clone https://github.com/Uvas12/Favorites2.git
```

La estructura debe quedar así:

```text
extensions/Favorites2/
```

2. Añade la siguiente línea a tu archivo `LocalSettings.php`:

```php
wfLoadExtension( 'Favorites2' );
```

3. Ejecuta el script de actualización de MediaWiki para crear la tabla necesaria:

```bash
php maintenance/run.php update
```

En versiones antiguas de MediaWiki, usa:

```bash
php maintenance/update.php
```

4. Visita `Especial:Versión` o `Special:Version` para confirmar que la extensión está cargada correctamente.

## Configuración

Favorites2 incluye dos variables de configuración opcionales.

### `$wgUseIconFavorite`

Controla si Favorites2 usa un icono de corazón o una acción de texto.

Valor por defecto:

```php
$wgUseIconFavorite = true;
```

Cuando está en `true`, Favorites2 muestra un icono de corazón en la barra principal de acciones de la página:

```php
$wgUseIconFavorite = true;
```

Cuando está en `false`, Favorites2 muestra la opción como texto dentro del menú **Más**:

```php
$wgUseIconFavorite = false;
```

### `$wgFavoritesPersonalURL`

Controla si se añade un enlace a favoritos en el menú personal del usuario.

Valor por defecto:

```php
$wgFavoritesPersonalURL = true;
```

Para desactivar el enlace personal:

```php
$wgFavoritesPersonalURL = false;
```

### Ejemplo de configuración

```php
wfLoadExtension( 'Favorites2' );

$wgUseIconFavorite = true;
$wgFavoritesPersonalURL = true;
```

Ejemplo usando modo texto:

```php
wfLoadExtension( 'Favorites2' );

$wgUseIconFavorite = false;
$wgFavoritesPersonalURL = true;
```

## Uso

### Añadir una página a favoritos

Si el modo icono está activado:

```php
$wgUseIconFavorite = true;
```

haz clic en el icono de corazón que aparece en la barra de acciones de la página.

Si el modo texto está activado:

```php
$wgUseIconFavorite = false;
```

abre el menú **Más** y selecciona:

```text
Añadir a favoritos
```

### Quitar una página de favoritos

Si una página ya está en favoritos, el mismo botón o enlace cambiará a:

```text
Quitar de favoritos
```

También puedes quitar páginas desde:

```text
Especial:Favorites
```

o:

```text
Special:Favorites
```

## Página especial

Favorites2 añade la página especial:

```text
Especial:Favorites
```

o en inglés:

```text
Special:Favorites
```

Esta página muestra las páginas favoritas del usuario actual.

La lista se organiza por namespace y se ordena alfabéticamente.

Cada elemento puede incluir enlaces para:

- abrir la página;
- editar la página;
- quitar la página de favoritos.

Los usuarios anónimos deben iniciar sesión para usar la página de favoritos.

## Modos de visualización

### Modo icono

Cuando:

```php
$wgUseIconFavorite = true;
```

Favorites2 muestra un corazón como icono en la barra principal de acciones de la página.

Este modo está diseñado para mantenerse fuera del menú **Más**, incluso si cambia la configuración visual de la pestaña de vigilancia de Vector.

### Modo texto

Cuando:

```php
$wgUseIconFavorite = false;
```

Favorites2 muestra una acción de texto dentro del menú **Más**.

Esto mantiene limpia la barra principal de pestañas.

## Tabla de base de datos

Favorites2 crea la tabla:

```sql
CREATE TABLE IF NOT EXISTS /*_*/favorites (
  favorite_id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  favorite_user_id   INT UNSIGNED NOT NULL,
  favorite_page_id   INT UNSIGNED NOT NULL,
  favorite_timestamp VARBINARY(14) NOT NULL DEFAULT '',
  PRIMARY KEY (favorite_id),
  UNIQUE KEY favorite_user_page (favorite_user_id, favorite_page_id),
  KEY favorite_user (favorite_user_id),
  KEY favorite_page (favorite_page_id)
) /*$wgDBTableOptions*/;
```

Favorites2 guarda los favoritos usando:

```text
favorite_page_id
```

Esto permite que los favoritos sigan funcionando aunque una página sea renombrada, porque MediaWiki normalmente conserva el mismo `page_id` al mover una página.

## Eliminación de páginas

Favorites2 limpia registros de favoritos cuando se eliminan páginas.

### Borrado normal

Cuando una página se elimina usando el sistema normal de MediaWiki, Favorites2 usa el hook:

```text
PageDeleteComplete
```

para borrar los registros asociados a esa página.

Esto elimina registros como:

```sql
DELETE FROM favorites
WHERE favorite_page_id = ID_DE_LA_PAGINA_BORRADA;
```

### Borrado permanente con DeletePagesForGood

Favorites2 también incluye compatibilidad con borrados permanentes realizados por la extensión DeletePagesForGood.

Cuando se detecta la acción:

```text
delete_page_permanently
```

Favorites2 elimina los registros asociados antes de que la página desaparezca de la tabla `page`.

Esto evita que queden registros huérfanos cuando una página se elimina permanentemente.

## Notas para instalaciones existentes

Si ya tenías instalada la extensión antes de añadir el índice `favorite_page`, puedes agregarlo manualmente:

```sql
ALTER TABLE favorites ADD INDEX favorite_page (favorite_page_id);
```

Este índice mejora el rendimiento al limpiar favoritos por página.

## Licencia

Esta extensión está publicada bajo la licencia MIT.

Consulta el archivo `LICENSE` para más información.
