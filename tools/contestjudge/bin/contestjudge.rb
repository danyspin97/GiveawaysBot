#!/usr/bin/env ruby

require 'pg'
require 'yaml'
require_relative '../lib/contestjudge'

@db = PG.connect(dbname: 'YOURDB', user: 'YOURUSER', password: 'YOURPASSWORD')
@database = Database.new(@db)

@giveaways = ContestJudge::Contest.new(database: @database)
@giveaways = @giveaways.fetch

@endpoint = "https://api.telegram.org/botEXAMPLETOKEN/sendMessage"
@localization = YAML.load_file(File.dirname($PROGRAM_NAME) + '/languages.yml')

@giveaways.each do |giveaway|
  judge = Judge.new(giveaway[1])
  judge.run
  judge.winners.each do |winner|
    user = winner[:identity]
    prize = winner[:prize]
    giveaway = prize['giveaway']
    message = @localization[user[:lang]]

    message = format(message, winner[:name], prize['name'], prize['value'],
                                             prize['currency'], prize['key'])

    @db.exec("INSERT INTO won VALUES(#{user[:id]}, #{giveaway}, #{prize['id']})")
    puts Curl.post(@endpoint, chat_id: user[:id], parse_mode: 'Markdown', text: message).body_str
  end
end