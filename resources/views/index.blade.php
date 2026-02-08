<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>VFF - Voies Ferrées de France</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}">
        @vite('resources/css/general.css')
        @vite('resources/css/index/index.css')
        @vite('resources/js/index/indexImport.js')
    </head>
    <body>
        <header>
            <div class="header-background">
                <nav>
                    <div class="nav-title">
                        <figure>
                            <img src="{{ Vite::image('vff.png') }}" alt="Logo de VFF">
                        </figure>
                        <h2>Voies Ferrées de France</h2>
                    </div>
                    <ul class="links">
                        <li class="whoarewe">
                            <a href="/whoarewe"><i class="bi bi-people"></i> Notre histoire</a>
                        </li>
                        <li class="maps">
                            <a href="/maps"><i class="bi bi-map"></i> Nos cartes</a>
                        </li>
                        <li class="contribute">
                            <a href="/contribute"><i class="bi bi-geo"></i> Votre contribution</a>
                        </li>
                        <li class="donate">
                            <a href="/donate"><i class="bi bi-balloon-heart"></i> Faites un don</a>
                        </li>
                        <ul class="user">
                            <li class="theme">
                                <button class="theme-change">
                                    <i class="bi bi-sun light-theme-icon fs-5" title="Thème clair"></i>
                                    <i class="bi bi-moon-stars dark-theme-icon fs-5 hidden-button" title="Thème sombre"></i>
                                </button>
                            </li>
                            <li class="lang">
                                <i class="bi bi-translate"></i>
                                <i class="bi bi-chevron-down"></i>
                            </li>
                            <li class="account">
                                <i class="bi bi-person-square"></i>
                                <i class="bi bi-chevron-down"></i>
                            </li>
                        </ul>
                    </ul>
                </nav>
                <div class="header-images">
                    <figure class="left-tgv">
                        <img src="{{ Vite::image('tgv-left.png') }}" alt="Queue du TGV">
                    </figure>
                    <figure>
                        <img src="{{ Vite::image('vff.png') }}" alt="Logo de VFF">
                    </figure>
                    <figure class="right-tgv">
                        <img src="{{ Vite::image('tgv-right.png') }}" alt="Tête du TGV">
                    </figure>
                </div>
                <div class="bottom-container" style="position: relative;">
                    <div class="scroll-button">
                        <span><i class="bi bi-chevron-double-down"></i></span>
                    </div>
                </div>
            </div>
        </header>
        <main>
            <section id="whoarewe" class="noTranslate">
                <div class="vff-title">
                    <img src="{{ Vite::image('vff.png') }}" alt="Logo de VFF">
                    <h1>VFF - À la croisée des rails</h1>
                </div>
                <p>Nous sommes VFF, une plateforme en ligne créée par deux passionnés du rail. Notre mission est de documenter
                    et de partager des informations sur les lignes ferroviaires, qu'elles soient en service ou fermées. Grâce à
                    nos cartes collaboratives basées sur des données d'archives et sur le travail de nombreux collaborateurs,
                    nous pouvons vous proposer l'une des plus grandes base de données concernant le chemin de fer amateur en
                    France. Vous pouvez contribuer, vous aussi, à enrichir cette base de données en complétant les infos des voies
                    ferrées que vous connaissez. De plus, notre API publique permet aux développeurs d'explorer et d'utiliser
                    nos données pour créer des applications innovantes dans le domaine ferroviaire. Rejoignez notre communauté
                    pour explorer le monde fascinant des chemins de fer et partager vos connaissances avec d'autres
                    passionnés.</p>
                <div class="whoarewe-buttons">
                    <a href="/whoarewe"><i class="bi bi-people"></i> Notre histoire</a>
                    <a href="/donate"><i class="bi bi-balloon-heart"></i> Faites un don</a>
                </div>
            </section>
            <section id="maps" class="noTranslate">
                <div class="maps-background"></div>
                <div class="maps-container">
                    <div class="maps-box">
                        <div>
                            <figure>
                                <img src="{{ Vite::image('vff.png') }}">
                            </figure>
                            <h2>Titre 1</h2>
                            <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. A ad aliquam aspernatur at
                                corporis, eligendi et explicabo harum inventore iste iusto modi nobis perferendis
                                quaerat quasi, repudiandae vel vero voluptatem?</p>
                        </div>
                    </div>
                    <div class="maps-box">
                        <div>
                            <figure>
                                <img src="{{ Vite::image('vff.png') }}">
                            </figure>
                            <h2>Titre 2</h2>
                            <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Animi assumenda aut deserunt
                                dolores doloribus earum exercitationem expedita facilis fugit illo inventore ipsam
                                libero praesentium quidem, rerum similique ullam! Esse, harum.</p>
                        </div>
                    </div>
                    <div class="maps-box">
                        <div>
                            <figure>
                                <img src="{{ Vite::image('vff.png') }}">
                            </figure>
                            <h2>Titre 3</h2>
                            <p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. A aliquid asperiores distinctio
                                id inventore modi numquam quaerat voluptatum? A assumenda illo illum, ipsam odit quidem
                                recusandae reiciendis vero voluptates voluptatum?</p>
                        </div>
                    </div>
                </div>
            </section>
            <section id="contribute" class="noTranslate">

            </section>
        </main>
        <footer>
            <div class="footer-infos">
                <div class="banner">
                    <figure>
                        <img src="{{ Vite::image('vff.png') }}">
                    </figure>
                    <p>Explorez le monde du rail français, plongez dans l'histoire riche et diversifiée de ces lignes qui traversent notre pays, et découvrez les moindres détails sur les trains qui les parcourent. Nous sommes passionnés par le patrimoine ferroviaire de la France, et notre objectif est de partager cette passion avec vous.</p>
                </div>
                <div class="footer-links">
                    <ul class="general-links">
                        <h3>Liens rapides</h3>
                        <li class="whoarewe">
                            <a href="/whoarewe"><i class="bi bi-people"></i> Notre histoire</a>
                        </li>
                        <li class="maps">
                            <a href="/maps"><i class="bi bi-map"></i> Nos cartes</a>
                        </li>
                        <li class="contribute">
                            <a href="/contribute"><i class="bi bi-geo"></i> Votre contribution</a>
                        </li>
                        <li class="donate">
                            <a href="/donate"><i class="bi bi-balloon-heart"></i> Faites un don</a>
                        </li>
                        <hr>
                        <li class="api">
                            <a href="/api"><i class="bi bi-cursor"></i> Accès à l'API</a>
                        </li>
                    </ul>
                    <ul class="contact-links">
                        <h3>Nous retrouver</h3>
                        <li class="discord">
                            <a href="https://www.discord.com/invite/6SwTfXBx4h"><i class="bi bi-discord"></i> Discord</a>
                        </li>
                        <li class="instagram">
                            <a href="https://www.instagram.com/vff_project"><i class="bi bi-instagram"></i> Instagram</a>
                        </li>
                        <li class="twitter-x">
                            <a href="https://www.x.com/vff-project"><i class="bi bi-twitter-x"></i> Twitter</a>
                        </li>
                        <li class="github">
                            <a href="https://www.github.com/vff-project"><i class="bi bi-github"></i> GitHub</a>
                        </li>
                        <li class="unxwares">
                            <a href="https://www.unxwares.studio/vff-project"><img src="{{ Vite::image('unxwares.png') }}"> UnxWares</a>
                        </li>
                    </ul>
                    <ul class="legal-links">
                        <h3>Légal</h3>
                        <li class="legal">
                            <a href="/legal"><i class="bi bi-folder"></i> Mentions légales</a>
                        </li>
                        <li class="privacy">
                            <a href="/privacy"><i class="bi bi-bell-slash"></i> Confidentialité</a>
                        </li>
                        <li class="use-conditions">
                            <a href="/use-conditions"><i class="bi bi-check2-circle"></i> CGU</a>
                        </li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="copyright">
                <p>Voies Ferrées de France - VFF © 2023-2024 - Tous droits réservés</p>
                <p>Propulsé par <a href="https://www.unxwares.studio/"><img src="{{ Vite::image('unxwares.png')}}"> UnxWares</a>. Développé par Baptiste Gosselin</p>
            </div>
        </footer>
    </body>
</html>
