module Tyche
  module Core
    class Updater
      attr_reader :giveaways

      def initialize(database)
        @db = database
        @today_date = Time.now.strftime('%Y-%m-%d')
        @giveaways = {}

        filename = "TYCHE_#{Time.now.strftime('%Y-%m-%d')}.log"
        @logger = Logger.new("/tmp/#{filename}")
      end

      def fetch
        @logger.info "** Retrieving giveaways which ends today.."
        query = "SELECT id, name, type FROM Giveaway WHERE last = '#{@today_date}'"
        execute(query) { |giveaway| add_giveaway(giveaway) }

        @logger.info "** Deleting giveaways which not have participants.."
        clear_giveaways

        @giveaways
      end

      private

      def execute(query, &block)
        @db.exec(query) do |result|
          result.each { |record| yield record }
        end
      end

      # In order to simplify things, we retrieve just the essential
      # data about the giveaway and its participants, nor its prize.
      def add_giveaway(giveaway)
        id = giveaway['id']
        @giveaways[id] = giveaway

        @giveaways[id]['participants'] = []
        add_giveaway_participants(id)
      end

      def add_giveaway_participants(giveaway_id)
        query = "SELECT chat_id FROM Joined WHERE giveaway_id = #{giveaway_id}"
        
        execute(query) do |participant|
          @giveaways[giveaway_id]['participants'] << participant['chat_id']
        end
      end

      # In order to save time, we remove giveaways which not have
      # participants before pass the hash to other classes.
      def clear_giveaways
        @giveaways.select! do |giveaway|
          @giveaways[giveaway]['participants'].size > 0
        end
      end
    end
  end
end
