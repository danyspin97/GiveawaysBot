require_relative '../lib/tyche'

include Tyche::Utils::Winners
include Tyche::Utils::Losers

@participants = {}
@decorator = "\n\n\n"

@language = Tyche::Core::Configuration.new('languages.yml')
@language.load

@config = Tyche::Core::Configuration.new('secrets.yml')
@config.load

ActiveRecord::Base.establish_connection(database: @config.options['database'],
                                        host: @config.options['host'],
                                        adapter: @config.options['adapter'],
                                        password: @config.options['password'],
                                        user: @config.options['user'])

@endpoint = "https://api.telegram.org/bot#{@config.options['token']}/sendMessage"

fetch_giveaways()
notify_winners()
notify_losers()
