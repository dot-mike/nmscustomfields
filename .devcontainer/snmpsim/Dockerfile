FROM python:3.11-slim

ADD entrypoint.sh /usr/bin/entrypoint.sh
RUN chmod +x /usr/bin/entrypoint.sh

RUN pip install snmpsim

RUN groupadd snmpsim && useradd snmpsim -g snmpsim

VOLUME /usr/snmpsim/data
USER snmpsim
EXPOSE 1161/udp

ENTRYPOINT ["entrypoint.sh", "--agent-udpv4-endpoint=0.0.0.0:1161"]