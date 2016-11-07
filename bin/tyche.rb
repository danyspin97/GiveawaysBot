require_relative '../lib/tyche'
include Tyche::Entities
include Tyche::Core

@db = PG.connect(dbname: 'giveawaybot_16',
                 host: 'localhost',
                 password: 'pr4kas&U',
                 user: 'dom')

@language = Tyche::Core::Configuration.new('languages.yml').load
@config = Tyche::Core::Configuration.new('secrets.yml').load

@endpoint = "https://api.telegram.org/bot#{@config['token']}/sendMessage"
@participants = {}

# Retrieve giveaways to parse
updater = Tyche::Core::Updater.new(@db)
giveaways = updater.fetch

# Create participants' hash
giveaways.each do |_, giveaway|
  giveaway['participants'].each do |participant|
    next if @participants[participant]

    @participants[participant] = Participant.new(participant, @db).participant
    @participants[participant][:losed] = []
    @participants[participant][:won] = {}
  end
end

# Calculate giveaways' winners and losers

giveaways.each do |_, giveaway|
  committer = Committer.new(giveaway, @db)
  committer.commit

  # Register losers and winners
  committer.losers.each do |loser|
    @participants[loser][:losed] << giveaway['name']
  end

  committer.winners.each do |winner, prize|
    @participants[winner][:won][giveaway['name']] = prize  
  end
end

# Send notifications

@participants.each do |participant|
  notification = Notification.new(participant, language: @language,
                                               token: @config['token'],
                                               endpoint: @endpoint)
  notification.send
end
