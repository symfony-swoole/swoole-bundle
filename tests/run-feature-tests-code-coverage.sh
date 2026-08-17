#!/usr/bin/env bash

# Runs the feature tests one file at a time, retrying each, and collects coverage from every one.
#
# The per-file retry is the point of this script - under xdebug the servers are slow enough to boot that
# a test times out now and then, and re-running that single file is far cheaper than re-running the suite.
# ParaTest cannot express "retry this file", so instead of handing the suite to it, the files are dealt
# out to shards that run side by side. Each shard exports a TEST_TOKEN of its own, which is what keeps the
# shards off each other's ports, var directories, pid files and databases - see
# SwooleBundle\SwooleBundle\Tests\Helper\TestToken.

SWOOLE=${SWOOLE:-unknown}
SHARDS=${PARATEST_PROCESSES:-4}
MAX_TRIES=5

# One worker owns this many consecutive ports, starting here. Mirrors TestToken.
PORT_BASE=9999
PORT_BLOCK_SIZE=8

# Deals the files out longest-first, each to whichever shard has the least queued so far.
#
# File size stands in for duration - a rough proxy, but the distribution is lopsided enough that it does
# not need to be a good one: a handful of files take minutes and the rest take seconds. Dealing them out
# round-robin instead leaves it to luck whether the two longest land in the same shard, and when they do,
# that shard alone sets the wall clock for the whole run.
deal_files() {
    local -a load
    local i best

    for ((i = 1; i <= SHARDS; i++)); do
        load[i]=0
        : > "/tmp/shard-${i}.files"
    done

    while read -r size file; do
        best=1
        for ((i = 2; i <= SHARDS; i++)); do
            if ((load[i] < load[best])); then
                best=$i
            fi
        done

        echo "${file}" >> "/tmp/shard-${best}.files"
        load[best]=$((load[best] + size))
    done < <(for f in ./tests/Feature/*.php; do printf '%s %s\n' "$(wc -c < "$f")" "$f"; done | sort -rn)
}

run_shard() {
    local shard=$1
    local exit_code=0

    # Everything this shard starts - the PHPUnit process and every server it spawns - reads this.
    export TEST_TOKEN="${shard}"

    while read -r f; do
        local test_no
        test_no=$(basename "$f" .php)

        echo "[shard ${shard}][test ${test_no}] $f"

        local test_exit_code=0
        for ((try_no = 1; try_no <= MAX_TRIES; try_no++)); do
            echo "[shard ${shard}][test ${test_no}] try ${try_no} of ${MAX_TRIES}"

            vendor/bin/phpunit "$f" \
                --coverage-php "cov/feature-tests-${SWOOLE}-${shard}-${test_no}.cov" \
                --colors=always
            test_exit_code=$?

            # Make sure this shard's ports are clear for the next file. Only this shard's block is
            # touched, so a shard can never take a server out from under a sibling.
            local first=$((PORT_BASE + (shard - 1) * PORT_BLOCK_SIZE))
            local last=$((first + PORT_BLOCK_SIZE - 1))
            local pids
            pids=$(lsof -t -i ":${first}-${last}" 2>/dev/null)
            if [[ -n "${pids}" ]]; then
                # shellcheck disable=SC2086
                kill -9 ${pids} || true
                sleep 1
            fi

            if [[ "${test_exit_code}" = "0" ]]; then
                break
            fi
            sleep 1
        done

        if [[ "${test_exit_code}" != "0" ]]; then
            exit_code=1
        fi
    done < "/tmp/shard-${shard}.files"

    return "${exit_code}"
}

deal_files

EXIT_CODE=0
PIDS=()

for ((shard = 1; shard <= SHARDS; shard++)); do
    run_shard "${shard}" &
    PIDS+=($!)
done

for pid in "${PIDS[@]}"; do
    wait "${pid}" || EXIT_CODE=1
done

exit ${EXIT_CODE}
