# Ferretería El Martillo — Inventario

Aplicación del **Cliente A** de WebLaunch Inc. (stack clásico).

- PHP 7.4 + Apache 2.4
- MySQL 5.7 (PDO)
- Bootstrap 3.4.1 + jQuery 1.12.4

CRUD de productos (crear, listar, editar, eliminar). La tabla se crea sola al arrancar.

## Variables de entorno

Ver `.env.example`. Ninguna credencial está en el código: el PaaS las inyecta
desde un Secret de Kubernetes.

## Despliegue

Cada `git push` a la rama principal es detectado por el PaaS de WebLaunch,
que construye la imagen y actualiza el servicio automáticamente.
