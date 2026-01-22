#!/usr/bin/env python3

import requests
import argparse
import sys
from bs4 import BeautifulSoup
from urllib.parse import urljoin, urlparse
from colorama import Fore, Style, init

# Initialisation des couleurs
init(autoreset=True)

# --- CONFIGURATION ARGUMENTS ---
parser = argparse.ArgumentParser(description="Audit SEO strict : Scanne uniquement le domaine cible.")
parser.add_argument("url", help="L'URL du site à scanner (ex: https://zfx.fr)")
parser.add_argument("--check-doublons", action="store_true", help="Active la détection des doublons avec/sans trailing slash")
args = parser.parse_args()

# --- PREPARATION URL & DOMAINE ---
start_url = args.url.rstrip('/')
if not start_url.startswith(('http://', 'https://')):
    start_url = 'https://' + start_url

# On extrait le domaine EXACT de départ (ex: 'zfx.fr' ou 'blog.zfx.fr')
target_domain = urlparse(start_url).netloc

visited = {}

def normalize_url(url):
    """Normalise l'URL : retire le fragment et gère le slash de fin selon l'option"""
    # Toujours retirer le fragment (#)
    url = url.split('#')[0]
    
    if args.check_doublons:
        # Si on cherche les doublons, on GARDE le slash s'il existe pour différencier /abc et /abc/
        return url
    else:
        # Sinon, on le retire pour éviter de scanner deux fois la même page
        return url.rstrip('/')

def is_internal(url):
    """Vérifie strictement si l'URL appartient au même domaine que celui de départ"""
    try:
        url_domain = urlparse(url).netloc
        # Si le domaine est vide (lien relatif) ou identique au domaine cible -> C'est bon
        return url_domain == target_domain or url_domain == ''
    except:
        return False

def get_page_details(url):
    try:
        headers = {'User-Agent': 'Mozilla/5.0 (compatible; SEO-Audit-Bot/1.0)'}
        response = requests.get(url, headers=headers, timeout=10)
        response.encoding = 'utf-8' 

        # On ignore les fichiers binaires (PDF, Images, etc.)
        content_type = response.headers.get('Content-Type', '')
        if response.status_code != 200 or 'text/html' not in content_type:
            return None

        soup = BeautifulSoup(response.text, 'html.parser')

        title = soup.title.string.strip().replace('\n', ' ') if soup.title else None
        
        desc_tag = soup.find('meta', attrs={'name': 'description'}) or soup.find('meta', attrs={'property': 'og:description'})
        description = desc_tag['content'].strip().replace('\n', ' ') if desc_tag and desc_tag.get('content') else None

        h1_tag = soup.find('h1')
        h1 = h1_tag.get_text().strip().replace('\n', ' ') if h1_tag else None

        return {'soup': soup, 'title': title, 'description': description, 'h1': h1}

    except KeyboardInterrupt:
        sys.exit(0)
    except Exception as e:
        # On n'affiche les erreurs que si ce n'est pas une interruption clavier
        # print(f"{Fore.RED}Erreur technique sur {url}: {e}")
        return None

def print_report(url, data):
    print(f"{Style.BRIGHT}{Fore.CYAN}{'-'*80}")
    print(f"{Style.BRIGHT}🔗 URL : {Fore.WHITE}{url}")

    # TITRE
    if not data['title']:
        print(f"  ❌ {Fore.RED}TITRE       : MANQUANT !")
    elif "TITRE ICI" in data['title']:
        print(f"  ⚠️  {Fore.RED}TITRE       : {data['title']} (ATTENTION : TITRE PAR DÉFAUT !)")
    else:
        print(f"  ✅ {Fore.GREEN}TITRE       : {Fore.RESET}{data['title']}")

    # DESCRIPTION
    if not data['description']:
        print(f"  ❌ {Fore.RED}DESCRIPTION : MANQUANTE !")
    elif len(data['description']) < 50:
        print(f"  ⚠️  {Fore.YELLOW}DESCRIPTION : {data['description']} (Un peu court...)")
    else:
        print(f"  ✅ {Fore.GREEN}DESCRIPTION : {Fore.RESET}{data['description'][:90]}...")

    # H1
    if not data['h1']:
        print(f"  ❌ {Fore.RED}BALISE H1   : MANQUANTE !")
    elif data['title'] and data['h1'] == data['title']:
         print(f"  ℹ️  {Fore.BLUE}BALISE H1   : Identique au Title")
    else:
        print(f"  ✅ {Fore.GREEN}BALISE H1   : {Fore.RESET}{data['h1']}")

def crawl(url, source_url=None):
    clean_url = normalize_url(url)

    # Sécurité : Si l'URL n'est pas interne, on s'arrête tout de suite
    if not is_internal(url):
        return

    if clean_url in visited:
        return
    
    # On enregistre l'URL visitée avec sa source (referrer)
    visited[clean_url] = source_url

    data = get_page_details(url)
    
    if data:
        print_report(url, data)

        for link in data['soup'].find_all('a', href=True):
            full_link = urljoin(url, link['href'])
            full_link_clean = normalize_url(full_link)

            # FILTRE STRICT : On ne suit le lien QUE si c'est le même domaine
            # Note : normalize_url retire déjà le hash, donc la vérification '#' est moins critique mais on garde par sécu
            if is_internal(full_link) and "#" not in full_link:
                # On évite aussi les extensions de fichiers statiques
                if not full_link.lower().endswith(('.png', '.jpg', '.jpeg', '.gif', '.pdf', '.css', '.js', '.zip', '.svg', '.webp')):
                    if full_link_clean not in visited:
                        crawl(full_link, source_url=url)

# --- LANCEMENT ---
if __name__ == "__main__":
    try:
        print(f"{Style.BRIGHT}{Fore.YELLOW}🚀 Démarrage de l'audit pour : {start_url}")
        print(f"{Style.BRIGHT}{Fore.YELLOW}🔒 Périmètre strict : {target_domain} uniquement.")
        if args.check_doublons:
            print(f"{Style.BRIGHT}{Fore.BLUE}🕵️  Option activée : Détection des doublons de trailing slash")
        print()
        
        crawl(start_url)
        
        print(f"\n{Style.BRIGHT}{Fore.YELLOW}🏁 Audit terminé. {len(visited)} pages scanées.")

        if args.check_doublons:
            print(f"\n{Style.BRIGHT}{Fore.BLUE}📦 ANALYSE DES DOUBLONS (Trailing slashes)...")
            doublons = []
            # On trie pour avoir un ordre stable
            visited_list = sorted(list(visited.keys()))
            
            for url in visited_list:
                # Si on a "monsite.com/page/" et que "monsite.com/page" existe aussi
                if url.endswith('/') and url[:-1] in visited:
                     doublons.append((url[:-1], url))
            
            if doublons:
                print(f"{Fore.RED}⚠️  {len(doublons)} paires de doublons détectées :")
                for u1, u2 in doublons:
                    src1 = visited.get(u1, "N/A")
                    src2 = visited.get(u2, "N/A")
                    print(f"\n  🔸 {Fore.WHITE}{u1}")
                    print(f"     ↳ Trouvé sur : {Style.DIM}{src1}{Style.NORMAL}")
                    print(f"  🔸 {Fore.WHITE}{u2}")
                    print(f"     ↳ Trouvé sur : {Style.DIM}{src2}{Style.NORMAL}")
                print(f"\n{Fore.LIGHTBLACK_EX}Conseil : Configurez vos redirections ou 'trailingSlash' dans votre framework pour n'en garder qu'une seule version.")
            else:
                print(f"{Fore.GREEN}✅ Aucun doublon de type trailing slash détecté.")

    except KeyboardInterrupt:
        print(f"\n{Fore.RED}Arrêt par l'utilisateur.")
