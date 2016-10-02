module Tyche
  module Core
    class Notification
      attr_reader :result

      def initialize(participant, **defaults)
        @locale = defaults[:locale]
        @participant = participant
        @messages = []
        @secret_key = defaults[:secret_key][0...16]

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
        key = Base64.decode64(key)
        decipher = OpenSSL::Cipher::AES128.new(:ECB)
        decipher.decrypt
        
        decipher.key = @secret_key
        decipher.iv = ""

        plain = decipher.update(key)
        plain = decipher.update(key)

        plain.force_encoding('utf-8').encode
      end
    end
  end
end
