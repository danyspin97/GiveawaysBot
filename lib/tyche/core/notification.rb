module Tyche
  module Core
    class Notification
      attr_reader :result

      def initialize(participant, **defaults)
        @locale = defaults[:locale]
        @participant = participant
        @messages = []

        @current_giveaway = nil
        parse_won_giveaway
      end

      def result
        @messages
      end

      private

      def parse_won_giveaway
        @participant[1][:won].each do |giveaway|
          @current_giveaway = giveaway
          message = generate_won_message

          @messages << message
        end
      end

      def generate_won_message
        giveaway = @current_giveaway
        lang = @participant[1][:lang]

        format(@locale[lang]['won_message'], giveaway[0], giveaway[1][:name],
                                             giveaway[1][:value],
                                             giveaway[1][:currency],
                                             decrypt_key(giveaway[1][:key]))
      end

      def decrypt_key(key)
        key
      end
    end
  end
end
