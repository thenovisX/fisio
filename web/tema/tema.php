<?php
declare(strict_types=1);
/*
 * CONFIGURACIÓN DEL TEMA. Solo constantes (lo leen también la API y el panel).
 * Tema «Fibra»: portada artística para centros de fisioterapia. Fondo de fibras en movimiento (Three.js),
 * scroll suave (Lenis), animaciones con GSAP + ScrollTrigger y títulos letra a letra (SplitType).
 * Todo se sirve desde tema/assets/vendor: la web no carga nada de otros dominios.
 * El contrato completo está en TEMA.md, en la raíz del repositorio.
 */

const TEMA_NOMBRE = 'Fibra';
/** Súbela cuando cambies el CSS o el JS del tema, para que los navegadores descarguen la versión nueva. */
const TEMA_VERSION = '1';

/** Colores para el icono de la app, la barra del navegador y el manifest. */
const TEMA_COLORES = [
    'fondo'  => '#0F1D1C',   // fondo del icono y color de la barra del navegador en el móvil
    'texto'  => '#EDE8DD',   // letra del icono
    'acento' => '#FF5B3A',   // destello del icono
];

/**
 * Huecos de foto. El cliente las cambia desde la app (Ajustes → Fotos de la web).
 * La primera es la principal (Google y redes sociales la usan como imagen de la web).
 * Sin foto propia, «centro» y «equipo» se pintan con el fondo de fibras: el diseño no depende de fotos de stock.
 */
const TEMA_FOTOS = [
    'portada' => [
        'label' => 'Imagen para Google y redes',
        'src' => 'tema/assets/img/portada.jpg',
        'alt' => 'Fibras de movimiento en coral y verde sobre fondo oscuro',
    ],
    'centro' => [
        'label' => 'El centro',
        'src' => '',
        'alt' => 'La sala de tratamiento del centro',
    ],
    'equipo' => [
        'label' => 'El equipo',
        'src' => '',
        'alt' => 'El equipo de fisioterapeutas',
    ],
];

/** ¿El tema incrusta un mapa de Google (con botón «Mostrar el mapa»)? Cambia el texto de cookies. */
const TEMA_GOOGLE_MAPS = false;
