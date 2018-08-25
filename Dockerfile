FROM node:10

WORKDIR /app

EXPOSE 80

COPY ./ /app

RUN npm install --production --unsafe-perm

CMD ["npm", "--production", "start"]
