{{-- Declaración de accesibilidad. Debe decir lo mismo que la matriz de docs/accesibilidad.md: al cambiar el estado de
     un criterio, revisar este texto y su fecha. No afirmar pruebas que no se hayan hecho. --}}
@php
    $tarjeta = 'rounded-2xl border border-brand-green/10 bg-white p-5 sm:p-8';
    $titulo = 'font-heading text-xl font-semibold text-brand-green';
    $lista = 'mt-3 list-disc space-y-2 pl-6';
@endphp

<div class="mx-auto w-full max-w-3xl space-y-6 px-4 py-8 leading-relaxed [overflow-wrap:anywhere] sm:px-6 lg:px-8">
    <header>
        <h1 class="font-heading text-3xl font-bold text-brand-green">Declaración de accesibilidad</h1>
        <p class="mt-2 text-sm text-gray-600">Última revisión: 27 de noviembre de 2026.</p>
    </header>

    <section class="{{ $tarjeta }}" aria-labelledby="acc-compromiso">
        <h2 id="acc-compromiso" class="{{ $titulo }}">Meta y estado</h2>
        <p class="mt-3">Queremos que cualquier persona pueda usar el Portal, el Dashboard y Tickets de la Zona Oriente. La meta es cumplir las Pautas de Accesibilidad para el Contenido Web (WCAG) 2.2, nivel AA.</p>
        <p class="mt-3"><strong>Estado actual: parcialmente conforme, en proceso.</strong> Se han corregido muchas barreras, pero todavía no se cumple ni se ha comprobado cada criterio, así que no declaramos conformidad.</p>
        <p class="mt-3">La revisión del 27 de noviembre de 2026 abarca el Portal, el Dashboard y Tickets. Combina una herramienta automática, pruebas automáticas de la estructura de las pantallas, la lectura del código y pruebas de teclado en un navegador de escritorio, siempre con datos de ejemplo. La herramienta automática solo detecta una parte de los problemas.</p>
    </section>

    <section class="{{ $tarjeta }}" aria-labelledby="acc-incluye">
        <h2 id="acc-incluye" class="{{ $titulo }}">Lo que ya puedes usar</h2>
        <p class="mt-3">El botón <strong>Accesibilidad</strong> (junto al selector de tema, o dentro del menú en el celular) abre un panel con:</p>
        <ul class="{{ $lista }}">
            <li>Tamaño de letra: 100, 112, 125 o 150 %.</li>
            <li>Contraste alto.</li>
            <li>Reducir movimiento.</li>
            <li>Subrayar enlaces.</li>
            <li>Tema claro, oscuro o el de tu dispositivo.</li>
        </ul>
        <p class="mt-3">La elección se guarda en tu navegador y se comparte entre los sistemas que usan el mismo dominio.</p>
        <p class="mt-3">Además, en los tres sistemas:</p>
        <ul class="{{ $lista }}">
            <li>Un enlace «Saltar al contenido» al principio de cada pantalla y un título propio en cada una.</li>
            <li>Los errores de los formularios se resumen arriba y se señalan en su campo.</li>
            <li>Un aviso antes de que venza la sesión, con la opción de seguir conectado.</li>
        </ul>
        <p class="mt-3">En el Portal, además, hay un botón para pausar el fondo animado, y el calendario mensual es una tabla con los días de la semana como encabezados, donde cada tipo de entrada tiene figura y nombre además de color.</p>
        <p class="mt-3">En el Dashboard, la hoja de cálculo se puede usar con teclado y tiene la alternativa «Ver como tabla», y los filtros se aplican con un botón.</p>
        <p class="mt-3">La guía <a href="{{ route('tutorials.show', 'accesibilidad') }}" class="font-semibold text-brand-teal underline">Accesibilidad</a> explica paso a paso cómo usar estas ayudas.</p>
    </section>

    <section class="{{ $tarjeta }}" aria-labelledby="acc-limites">
        <h2 id="acc-limites" class="{{ $titulo }}">Limitaciones conocidas</h2>
        <ul class="{{ $lista }}">
            <li>No se han hecho pruebas con lectores de pantalla ni con personas usuarias de apoyos técnicos.</li>
            <li>No se ha completado la revisión en celulares reales, con ampliación del 200 y 400 %, ni con más espaciado del texto.</li>
            <li>Falta medir el contraste de los textos colocados sobre fotografías y el de bordes, iconos e indicadores de foco.</li>
            <li>Las capturas de los tutoriales son imágenes con una descripción breve: el texto que aparece dentro de ellas no se puede ampliar ni leer por separado.</li>
            <li>En el Dashboard, la hoja de cálculo y la hoja de comprobación impresa conservan su tamaño de letra; la vista «Ver como tabla» sí crece, pero no ofrece formato ni calcula fórmulas.</li>
            <li>En el Dashboard, la zona «Cargar documento» sube el archivo en cuanto se elige y, si reconoce una tabla, abre otra pantalla sin avisarlo antes.</li>
            <li>En Tickets, la leyenda de la gráfica circular depende del color, y cuando una acción se rechaza por una regla del sistema el mensaje no queda señalado en un campo.</li>
            <li>En el aviso de fin de sesión aparece el botón «Volver a entrar» aun cuando la sesión sigue activa; para continuar usa «Seguir conectado».</li>
            <li>Los archivos que generan los sistemas (Excel, Word y PDF) no se han revisado.</li>
        </ul>
    </section>

    <section class="{{ $tarjeta }}" aria-labelledby="acc-reportar">
        <h2 id="acc-reportar" class="{{ $titulo }}">Cómo reportar un problema</h2>
        <p class="mt-3">Si algo no se puede usar, avisa a la administración del portal (quienes aprueban las cuentas en tu Zona). Si tu cuenta tiene acceso a Tickets, también puedes crear un ticket con el botón <strong>Nuevo ticket</strong>.</p>
        <p class="mt-3">Ayuda mucho que indiques:</p>
        <ul class="{{ $lista }}">
            <li>La pantalla o dirección donde ocurre.</li>
            <li>Qué intentabas hacer y qué pasó.</li>
            <li>Tu dispositivo, navegador y, si usas alguno, el apoyo técnico (por ejemplo, un lector de pantalla).</li>
        </ul>
    </section>
</div>
