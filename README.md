# hc_to_csv
Ecwid HC import to CSV

Paso 1: index.php "rasura" los nombres de archivo de la carpeta y deja unicamente los nombres de archivo sin texto, es decir, solo conservarían el ID que es de 9 a 14 digitos numericos.

Paso 2: file_processor.php modifica los archivos HTML en su texto para actualizar todas las ligas HTML a los nuevos nombres de archivo rasurados.

Paso 3: csv_creator.php crea un CSV a partir de los archivos html actualizados internamente en sus ligas. Se crea un CSV con el ID del articulo en HTML, un Titulo/H1, una cateogría y el contenido propio del artículo en HTML.
