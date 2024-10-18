FROM python:3.11-slim

ADD entrypoint.sh /usr/bin/entrypoint.sh
RUN chmod +x /usr/bin/entrypoint.sh

RUN pip install pysnmp snmpclitools snmpsim

RUN adduser --system snmpsim

VOLUME /usr/snmpsim/data
EXPOSE 1161/udp

ENTRYPOINT ["entrypoint.sh", "--agent-udpv4-endpoint=0.0.0.0:1161", "--process-user=snmpsim", "--process-group=nogroup", "--data-dir=/usr/snmpsim/data"]