#!/bin/bash
rsync -av --delete wp-content/plugins/templ-stickers/ etiketter:~/app_18882/wp-content/plugins/templ-stickers/ --exclude node_modules
rsync -av wp-content/mu-plugins etiketter:~/app_18882/wp-content/
rsync -av wp-content/acf-json etiketter:~/app_18882/wp-content/
