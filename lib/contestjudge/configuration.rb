module ContestJudge
  ##
  # Gets user's options from a configuration file.
  class Configuration
    attr_reader :options

    def initialize(path, loader = YAML)
      @path = path
      @options = {}

      @loader = loader
    end

    def load
      configuration_origin = expand_path
      @options = @loader.load(parse_inline_erb(configuration_origin))
    end

    private

    def expand_path
      File.dirname($PROGRAM_NAME) + '/' + @path
    end

    def parse_inline_erb(template)
      ERB.new(File.read(template)).result
    end
  end
end
