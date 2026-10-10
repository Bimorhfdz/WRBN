FROM node:22-alpine
WORKDIR /app
COPY package*.json ./
RUN npm install --omit=dev
COPY . .
RUN mkdir -p /data /data/uploads
EXPOSE 3000
CMD ["sh", "-c", "npm run db:admin && npm start"]
