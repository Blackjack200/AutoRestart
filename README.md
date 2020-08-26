AutoRestart
===
**Just support Unix System**
restart -t [second/n(now)]
 for example:
  - restart -t 15 (restart in 15 second)
  - restart -t n (restart now)

restart -st [second]
 for example:
  - restart -st 10(Set automatic restart time to 10 second)

restart -p [PATH TO SCRIPT]
 for example:
  - restart -p (absolute path to script)
  - restart -p %SP%xxx.sh
