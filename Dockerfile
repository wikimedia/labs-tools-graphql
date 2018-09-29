FROM node:10

WORKDIR /app

EXPOSE 80

COPY ./ /app

RUN npm install --production --unsafe-perm \
	&& npm run-script build

CMD ["npm", "--production", "start"]
