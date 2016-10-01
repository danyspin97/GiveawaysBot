module Tyche
  module Core
    class Commit
      attr_reader :participants

      def initialize(giveaway)
        @giveaway = giveaway
        @participants = {}
        @current_winners = []
      end

      def run
        initialize_participants
        assign_prizes
        mark_losers
      end

      private

      def initialize_participants
        @giveaway[:participants].each do |participant|
          @participants[participant[:chat_id]] = { lang: participant[:language],
                                                   won:   [],
                                                   losed: [] }
        end
      end

      def assign_prizes
        case @giveaway[:details][:type]
        when 'cumulative'
          assign_for_points
        else
          assign_prizes_randomly
        end
      end

      def mark_losers
        @participants.each_key do |participant|
          next if @current_winners.include?(participant)
          @participants[participant][:losed] << @giveaway[:details][:name]
        end

        @current_winners = []
      end

      def assign_for_points
        @giveaway[:prizes].each_with_index do |prize, index|
          participant = @participants.keys[index]
          winner = @participants[participant]
          break unless winner

          @current_winners << participant
          register_prize(prize)
        end
      end

      ##
      # In this case we use the participants' hash by @giveaway
      # in order to delete the winner and avoid that the winner is
      # choosen another time.
      def assign_prizes_randomly
        @giveaway[:prizes].each do |prize|
          break if @giveaway[:participants].empty?

          winner = rand(@giveaway[:participants].size)
          @current_winners << @giveaway[:participants][winner][:chat_id]
          register_prize(prize)

          @giveaway[:participants].delete_if do |hash|
            hash[:chat_id] == @current_winners.last
          end
        end
      end

      def register_prize(prize)
        winner_id = @current_winners.last

        @participants[winner_id][:won] << [@giveaway[:details][:name], prize]

        Tyche::Entities::Winner.create(chat_id: winner_id, id_prize: prize[:id],
                                       giveaway_id: @giveaway[:details][:id])
      end
    end
  end
end
