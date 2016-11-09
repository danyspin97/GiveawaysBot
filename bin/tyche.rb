require_relative '../lib/tyche'
include Tyche::Entities
include Tyche::Core

@language = Tyche::Core::Configuration.new('languages.yml').load
@config = Tyche::Core::Configuration.new('secrets.yml').load

@db = PG.connect(dbname: @config['dbname'],
                 host: @config['dbhost'],
                 password: @config['dbpasswd'],
                 user: @config['dbuser'])

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
