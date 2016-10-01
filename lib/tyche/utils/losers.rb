module Tyche
  module Utils
    module Losers
      def notify_losers()
        @participants.each do |participant, details|
          locale = details[:lang]
          @messages = []
          @messages << @language.options[locale]['lose_message']

          next if @participants[participant][:losed].empty?

          generate_loser_messages(details)
          send_messages(participant)
        end
      end

      private

      def generate_loser_messages(details)
        details[:losed].uniq.each do |giveaway|
          partial = "- *#{giveaway}*\n"

          if (@messages[-1] + partial).size > 4096
            @messages << partial
          else
            @messages[-1] << partial
          end
        end
      end

      def send_messages(participant)
        @messages.each do |message|
          Curl.post(@endpoint, chat_id: participant, text: message,
                               parse_mode: 'Markdown')
        end
      end
    end
  end
end
