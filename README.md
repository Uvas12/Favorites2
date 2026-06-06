# Extensión Favorites para MediaWiki 1.45+

Permite a los usuarios guardar páginas favoritas con un corazón animado ❤️

## Instalación

1. Descomprime la carpeta `Favorites` en `/extensions/Favorites/`

2. Crea la tabla en la base de datos:
   ```sql
   -- Desde la raíz del wiki:
   php maintenance/run.php sql extensions/Favorites/sql/favorites.sql
   ```
   O ejecuta el SQL directamente en tu base de datos.

3. Añade a tu `LocalSettings.php`:
   ```php
   wfLoadExtension( 'Favorites2'' );
   ```

4. Configuración opcional en `LocalSettings.php`:
   ```php
   // true = icono de corazón junto a la estrella de seguimiento (por defecto)
   // false = pestaña con texto en las acciones de la página
   $wgUseIconFavorite = true;

   // true = enlace "Favoritos" en el menú de usuario (por defecto)
   // false = sin enlace personal
   $wgFavoritesPersonalURL = true;
   ```

## Uso

- Visita cualquier página y haz clic en el **corazón ❤** que aparece
  junto a la estrella de seguimiento para añadirla/quitarla de favoritos.
- Accede a tu lista de favoritos desde el menú de usuario → **Favoritos**
  o desde `Especial:Favoritos`.
- En la lista puedes editar o eliminar páginas de tus favoritos.
- Los favoritos están ordenados por **namespace** y **alfabéticamente**.

## Características

- Corazón animado con efecto de "pop" y partículas al añadir
- AJAX sin recarga de página
- Notificaciones de éxito/error
- Dos modos: icono (junto a la estrella) o pestaña
- Lista ordenada por namespace y alfabéticamente
- Compatible con MediaWiki 1.45 / PHP 8.4
