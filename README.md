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
few Curl commands

- get a video
```
curl -XGET 'http://localhost/get_video' \
  -H 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8' \
  -H 'Authorization: am9obi5kb2U='

```
- add a video on the pool
```
curl -XPOST 'http://localhost/add_video' -d '{ "video_id": VIDEO_ID }'
```
- get a video
```
curl -XPOST 'http://localhost/get_video'  --header 'Authorization: am9obi5kb2U='
```

- get a video info (proxy)
```
curl -XGET 'http://localhost/get_video_info/VIDEO_ID
```

- flag a video
```
curl -XPOST 'http://localhost/flag_video' -d '{ "video_id" : VIDEO_ID, "status": "spam"|"not_spam" }'  --header 'Token: YWRtaW5fdG9rZW5fZGFpbHltb3Rpb24='
```

- log video
```
curl -XGET 'http://localhost/log_video/VIDEO_ID'
```

- get stats from the pool of videos
```
curl -XGET 'http://localhost/stats --header 'Token: am9obi5kb2U='
```

### Few things for the moderator (user)
- add a video create an initial log (logs allow to track changes made by moderator).
- once moderator get a video, she needs to work on it until flag the video in spam or not_spam. Thats means /get_video will return the same video for a moderator until she flags it.
- once a video is flagged by moderator, she could not change the status of the video (no more access)
- /stats is available only for *admin* moderator with a token security encoded in base_64 `admin_token_dailymotion`


