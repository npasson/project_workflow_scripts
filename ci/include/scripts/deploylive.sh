#!/usr/bin/env bash

# remove a line from the sources list
# not relevant to the script functionality, but it circumvents an error
sed -i '/jessie-updates/d' /etc/apt/sources.list
if [[ "${LIVE_USE_SFTP}" == "false" ]]; then
    # use NCFTPPUT, basically a fancy FTP upload tool
    # update apt packages and install ncftp
    apt-get update -yqq && apt-get install -y -qq ncftp;
    # just to make sure =)
    chmod -R 777 build
    # upload:
    #
    #                                                      remote directory (TODO: custom dir)
    #     verbose output                                     live server    │
    #  recursive │    live FTP user     live FTP password        │          │   local directory
    #         │  │ ┌────────┴────────┐ ┌────────┴────────┐ ┌─────┴──────┐ ┌─┴─┐ ┌───┴───┐
    ncftpput -R -v -u ${LIVE_FTP_USER} -p ${LIVE_FTP_PASS} ${LIVE_SERVER} /html ./build/*
else
    # use RSYNC for SSH
    # update apt packages and install rsync
    apt-get update -yqq && apt-get install -y -qq ssh rsync
    # upload:
    #
    #             don't upload CSS maps                          remote directory (TODO: custom dir)
    #  suppress non-errors │                     live SSH user                    │
    # recursive ┐│         │      local directory      │          live server     │
    #           ││ ┌───────┴───────┐ ┌───┴───┐ ┌───────┴───────┐ ┌─────┴──────┐ ┌─┴──┐
    rsync      -rq --exclude="*.map" ./build/* ${LIVE_SFTP_USER}@${LIVE_SERVER}:/html/
fi
