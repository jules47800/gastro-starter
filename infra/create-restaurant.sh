#!/bin/bash
set -euo pipefail

SLUG="${1:?Usage: $0 <slug> <domaine>}"
DOMAIN="${2:?Usage: $0 <slug> <domaine>}"
BASE_DIR="/srv/restaurants"
INSTANCE_DIR="$BASE_DIR/$SLUG"
THEME_SOURCE="/srv/themes/gastro-starter"

DB_PASS=$(openssl rand -base64 18)
WP_ADMIN_PASS=$(openssl rand -base64 12)

echo "=== Creation instance '$SLUG' ($DOMAIN) ==="

if [ ! -d "$THEME_SOURCE" ]; then
    echo "ERREUR: Theme absent dans $THEME_SOURCE"
    exit 1
fi

mkdir -p "$INSTANCE_DIR"

cat > "$INSTANCE_DIR/docker-compose.yml" <<COMPEOF
services:
  db:
    image: mariadb:11
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: wordpress
      MARIADB_USER: wp_${SLUG}
      MARIADB_PASSWORD: ${DB_PASS}
      MARIADB_ROOT_PASSWORD: ${DB_PASS}_root
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - internal

  wordpress:
    image: wordpress:6-php8.2-apache
    restart: unless-stopped
    depends_on:
      - db
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_NAME: wordpress
      WORDPRESS_DB_USER: wp_${SLUG}
      WORDPRESS_DB_PASSWORD: ${DB_PASS}
      WORDPRESS_TABLE_PREFIX: wp_
    volumes:
      - wp_data:/var/www/html
      - ./theme:/var/www/html/wp-content/themes/gastro-starter
    networks:
      - internal
      - traefik_network
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.${SLUG}.rule=Host(\\\`${DOMAIN}\\\`)"
      - "traefik.http.routers.${SLUG}.entrypoints=websecure"
      - "traefik.http.routers.${SLUG}.tls.certresolver=letsencrypt"
      - "traefik.http.services.${SLUG}.loadbalancer.server.port=80"

volumes:
  db_data:
  wp_data:

networks:
  internal:
  traefik_network:
    external: true
COMPEOF

cp -r "$THEME_SOURCE" "$INSTANCE_DIR/theme"

cd "$INSTANCE_DIR"
docker compose up -d

echo "Attente demarrage WordPress..."
sleep 20

docker compose exec -T wordpress bash -c "
  if ! command -v wp &>/dev/null; then
    curl -sO https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x wp-cli.phar
    mv wp-cli.phar /usr/local/bin/wp
  fi

  for i in \$(seq 1 15); do
    wp db check --allow-root 2>/dev/null && break
    sleep 2
  done

  wp core install \
    --url=\"https://${DOMAIN}\" \
    --title=\"Restaurant Demo\" \
    --admin_user=admin \
    --admin_password=\"${WP_ADMIN_PASS}\" \
    --admin_email=\"admin@${DOMAIN}\" \
    --locale=fr_FR \
    --allow-root

  wp theme activate gastro-starter --allow-root
  wp rewrite structure '/%postname%/' --allow-root

  wp post create --post_type=page --post_title='Accueil' --post_name='accueil' --post_status=publish --allow-root
  wp post create --post_type=page --post_title='Reserver' --post_name='reserver' --post_status=publish --allow-root
  wp post create --post_type=page --post_title='Menus' --post_name='menus' --post_status=publish --allow-root
  wp post create --post_type=page --post_title='Galerie' --post_name='galerie' --post_status=publish --allow-root
  wp post create --post_type=page --post_title='Bon Cadeau' --post_name='bon-achat' --post_status=publish --allow-root
  wp post create --post_type=page --post_title='Merci' --post_name='merci' --post_status=publish --allow-root
  wp post create --post_type=page --post_title='Politique de confidentialite' --post_name='politique-confidentialite' --post_status=publish --allow-root

  FRONT_ID=\$(wp post list --post_type=page --name=accueil --format=ids --allow-root)
  wp option update show_on_front page --allow-root
  wp option update page_on_front \$FRONT_ID --allow-root
  wp option update timezone_string Europe/Paris --allow-root
  wp option update date_format d/m/Y --allow-root
  wp option update time_format H:i --allow-root
"

cat > "$INSTANCE_DIR/.credentials" <<CREDEOF
DOMAIN=$DOMAIN
SLUG=$SLUG
WP_ADMIN_USER=admin
WP_ADMIN_PASS=$WP_ADMIN_PASS
DB_USER=wp_${SLUG}
DB_PASS=$DB_PASS
CREDEOF
chmod 600 "$INSTANCE_DIR/.credentials"

echo ""
echo "========================================="
echo " Instance creee !"
echo "========================================="
echo " URL:      https://${DOMAIN}"
echo " Admin:    https://${DOMAIN}/wp-admin"
echo " Login:    admin"
echo " Password: ${WP_ADMIN_PASS}"
echo "========================================="
