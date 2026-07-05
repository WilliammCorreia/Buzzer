/*
 * Point d'entrée AssetMapper : importe la feuille Tailwind et active
 * les petits comportements UI (menu mobile).
 */
import './styles/app.css';

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('nav-toggle');
    const menu = document.getElementById('mobile-menu');

    toggle?.addEventListener('click', () => {
        const opened = menu?.classList.toggle('hidden') === false;
        toggle.setAttribute('aria-expanded', opened ? 'true' : 'false');
    });
});
