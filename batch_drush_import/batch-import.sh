#!/bin/bash
default='5'
THREADS=${1:-$default}


## associative array for job status
declare -a JOBS

## run command in the background
background() {
  eval $1 & JOBS[$!]="$1"
}

## check exit status of each job
## preserve exit status in ${JOBS}
## returns 1 if any job failed
reap() {
  local cmd
  local status=0
  for pid in ${!JOBS[@]}; do
    cmd=${JOBS[${pid}]}
    wait ${pid} ; JOBS[${pid}]=$?
    if [[ ${JOBS[${pid}]} -ne 0 ]]; then
      status=${JOBS[${pid}]}
      echo -e "[${pid}] Thread Exited with status: ${status}\n${cmd}"
    fi
  done
  return ${status}
}


# Concurrent thread wrapper
for THREAD in $THREADS; do
	# Add ampersand (&) at the end to send the task to the background
  background 'drush config-import -y 2>&1 && sleep 3'
done

reap || echo "Some jobs failed, retrying until timeout"



