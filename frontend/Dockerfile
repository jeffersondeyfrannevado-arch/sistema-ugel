# ---------- Etapa 1: compilar la app React/Vite ----------
FROM node:22-alpine AS build
WORKDIR /app

# URL publica de la API (Railway la pasa como build arg desde las variables del servicio)
ARG VITE_API_URL
ENV VITE_API_URL=$VITE_API_URL

COPY package.json package-lock.json ./
RUN npm ci --include=dev

COPY . .
RUN npm run build

# ---------- Etapa 2: servir los archivos estaticos con Nginx ----------
FROM nginx:1.27-alpine

# La imagen oficial de nginx procesa /etc/nginx/templates/*.template con envsubst,
# asi el puerto dinamico $PORT de Railway se inyecta al arrancar.
ENV PORT=8080
COPY nginx.conf.template /etc/nginx/templates/default.conf.template
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 8080
