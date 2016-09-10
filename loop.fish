#!/usr/bin/env fish

redis-cli set Giveaway:error 0
while true
  php adjustOffset.php
  set error (redis-cli get Giveaway:error)
  if math "$error==1" > /dev/null
    redis-cli incr Giveaway:offset
    php adjustOffset.php
  end
  php getUpdates.php
end
