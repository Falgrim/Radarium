import './bootstrap';

import jQuery from 'jquery';
window.$ = jQuery;

import '../scss/app.scss'; // out SCSS file
import * as bootstrap from 'bootstrap' // Bootstrap JS files

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
