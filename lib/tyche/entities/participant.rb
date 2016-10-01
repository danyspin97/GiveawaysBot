module Tyche
  module Entities
    class Participant < ActiveRecord::Base
      self.table_name = 'joined'
      self.inheritance_column = 'unknown'
    end
  end
end
