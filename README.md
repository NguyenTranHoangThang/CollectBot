# CollectBot
## Installation

```bash
docker-compose up -d
```
change .env DATABASE_URL=mysql://root:root@127.0.0.1:3306/bot
```bash
php bin/console doctrine:migrations:migrate
```
change .env DATABASE_URL=mysql://root:root@mysql:3306/bot