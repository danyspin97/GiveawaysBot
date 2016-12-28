module Tyche
  module Core
    ##
    # Calculate winners for the given giveaway and register it
    # into the database.
    class Committer
      attr_reader :winners, :losers

      def initialize(giveaway, database)
        @giveaway = giveaway
        @db = database
        @prizes = []

        @winners = {}

        filename = "TYCHE_#{Time.now.strftime('%Y-%m-%d')}.log"
        @logger = Logger.new("/var/log/#{filename}")
      end

      def commit
        @logger.info "** Getting prizes for #{@giveaway['name']}"
        retrieve_prizes

        @logger.info "** Assigning prizes for #{@giveaway['name']}"
        assign_prizes

        @logger.info "** Done! Assigned #{@winners.size} prizes\n"
      end

      def losers
        @giveaway['participants']
      end

      private

      def retrieve_prizes
        query = %Q{
          SELECT id, name, key, value, currency FROM prize
          WHERE giveaway_id = #{@giveaway['id']}
        }.strip

        @db.exec(query) do |result|
          result.each { |prize| @prizes << prize }
        end
      end

      # This method acts as dispatch looking for giveaway's type
      # and calling the right sub-method.
      def assign_prizes
        case @giveaway['type']
        when 'cumulative'
          assign_prizes_per_points
        when 'standard'
          assign_prizes_randomly
        end
      end

      # Assign prizes in randomly mode for standard giveaways.
      def assign_prizes_randomly
        @participants_amount = @giveaway['participants'].size

        @prizes.each do |prize|
          # A control that stop the prizes assignment if the prizes
          # are more than the participants.
          break if @giveaway['participants'].empty?
          winner_index = rand(@participants_amount)

          winner = @giveaway['participants'][winner_index]
          @winners[winner] = prize
          register_winner(winner, prize['id'])

          @giveaway['participants'].delete_at(winner_index)
          @participants_amount -= 1
        end
      end

      # Assign prizes looking at participants' points.
      def assign_prizes_per_points
        query = %Q{
          SELECT chat_id FROM Joined
          WHERE  giveaway_id = #{@giveaway['id']} ORDER BY invites DESC
          LIMIT #{@prizes.size}
        }.strip

        @db.exec(query) do |result|
          result.each_with_index do |participant, index|
            break unless @prizes[index]
            @winners[participant['chat_id']] = @prizes[index]

            register_winner(participant['chat_id'], @prizes[index]['id'])
            @giveaway['participants'].delete(participant['chat_id'])
          end
        end
      end

      # Add winner to the winners' table.
      def register_winner(winner_id, prize_id)
        query = %Q{
          INSERT INTO Won VALUES(#{winner_id}, #{@giveaway['id']}, #{prize_id})
        }.strip

        @db.exec(query)
      end
    end
  end
end
