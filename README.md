## Dailymotion Proxy

## Install

### Build docker containers
```
docker-compose build
```

### Start the application
```
docker-compose up -d
```
### Run database migrations
```
docker-compose exec php bin/console doctrine:migrations:migrate
````

### Run PHPUnit tests

Intall the database test
```
docker-compose exec php bin/console doctrine:database:create --env=test
docker-compose exec php bin/console doctrine:migrations:migrate --env=test
```

Run the tests
```
docker-compose exec --env=test php bin/phpunit
```

## Try it locally
few curls commands

- get a video
```
curl 'http://localhost/get_video' \
  -H 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8' \
  -H 'Authorization: am9obi5kb2U='

```

- add a video on the pool
```
curl -XPOST 'http://localhost/add_video' -d '{ "video_id": 123456 }'
```

- flag a video
```
curl -XPOST 'http://localhost/flag_video' -d '{ "video_id" : "456XJKLJKL", "status": "not spam" }'  --header 'Authorization: am9obi5kb2U='
```
- get video info from the proxy server
```
 curl http://localhost/get_video_info/787987
 ```
