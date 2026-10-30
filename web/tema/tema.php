<?php
declare(strict_types=1);
/*
 * CONFIGURACIÓN DEL TEMA. Solo constantes (lo leen también la API y el panel).
 * Este es el tema «Base»: un diseño sencillo para empezar. Para un proyecto nuevo, cambia lo que hay
 * en web/tema/ y nada más. El contrato completo está en TEMA.md, en la raíz del repositorio.
 */

const TEMA_NOMBRE = 'Base';
/** Súbela cuando cambies el CSS o el JS del tema, para que los navegadores descarguen la versión nueva. */
const TEMA_VERSION = '9';

/** Colores para el icono de la app, la barra del navegador y el manifest. */
const TEMA_COLORES = [
    'fondo'  => '#F6F2EC',   // fondo del icono y color de la barra del navegador en el móvil
    'texto'  => '#2A2420',   // letra del icono
    'acento' => '#B4532A',   // destello del icono
];

/**
 * Huecos de foto. El cliente las cambia desde la app (Ajustes → Fotos de la web).
 * La primera es la principal (Google y redes sociales la usan como imagen de la web).
 * 'src' puede ser una foto de Unsplash (se sirve desde tu servidor) o un archivo del tema: 'tema/assets/img/sala.jpg'.
 */
const TEMA_FOTOS = [
    'portada' => [
        'label' => 'Portada',
        'src' => 'https://images.unsplash.com/photo-1758405155772-ef0f0077375c',
        'alt' => 'La sala del restaurante con luz cálida',
        'by' => 'Aconitum', 'url' => 'https://unsplash.com/photos/ORAV7_PAXAE',
    ],
    'sala' => [
        'label' => 'Nosotros',
        'src' => 'https://images.unsplash.com/photo-1687369595840-e96a912586f1',
        'alt' => 'Cocinero emplatando en la cocina',
        'by' => 'Gastro Editorial', 'url' => 'https://unsplash.com/photos/Rp0KQRcQfDc',
    ],
    'plato' => [
        'label' => 'Carta',
        'src' => 'https://images.unsplash.com/photo-1612929633738-8fe44f7ec841',
        'alt' => 'Plato de la casa',
        'by' => 'ikhsan baihaqi', 'url' => 'https://unsplash.com/photos/pbc2wXbQYpI',
    ],
];

/** ¿El tema incrusta un mapa de Google (con botón «Mostrar el mapa»)? Cambia el texto de cookies. */
const TEMA_GOOGLE_MAPS = false;
